<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\EventListener;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\TransientFailureClassifier;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\CommitMark;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

use function array_keys;
use function in_array;
use function is_string;
use function str_starts_with;
use function strstr;

/**
 * 1.31 (REQ-RUNTIME-003 rule 6): a lock conflict of the bundle answers a conflict instead of a 500.
 *
 * - Lock conflict (a deadlock, lock wait timeout or serialization failure anywhere down the chain, a
 *   {@see TransientStorageFailure}, or a lost savepoint of unknown outcome): the status of the
 *   {@see ConflictResponsePolicy} (**409** by default, 503 when the host configures it) with a {@see ConflictCode} in the
 *   `X-Conflict-Code` header:
 *   - `CONFLICT_001` with `Retry-After` when nothing of the request was committed;
 *   - `CONFLICT_002` without `Retry-After` when part of it may be: anything committed since the request started
 *     (transaction-doctrine's request mark, {@see ConflictCode::forRequest()}, when its bundle is registered with
 *     `commit_detection`; and the bundle's own mark on the {@see CommitLedger} the commit-tracking middleware feeds),
 *     a committed `ConcurrencyConflict`, or a lost savepoint with no deadlock behind it.
 * - A unique key refusing an admin save (cookie name per profile, one translation per locale): 409 with the domain code
 *   {@see self::DUPLICATE_KEY} in `X-Error-Code`, never a `CONFLICT_*` code (it is not a lock conflict). Anywhere else a
 *   unique violation, a lost connection or any other storage error is not mapped: a real error.
 *
 * Two entry points, both for the bundle's own work only (the host's routes keep the host's error handling):
 * - {@see self::onException()}: the bundle's controllers (admin pages, config API);
 * - {@see self::httpExceptionFor()}: the consent POST, handled by `CookieConsentFormSubscriber` on the response of
 *   whatever page the form posted to; it throws the answer only for a failure of its own consent write.
 *
 * The pages are rendered by the application's error page: the answer is an HttpException with a fixed, client-safe
 * message and the storage error as previous (for the logs; no SQL or value reaches the client).
 *
 * After a failure on a bundle route the bundle's entity manager is replaced when a failed flush closed it (it is
 * never cleared or reset while open, REQ-RUNTIME-003 rule 2). Stateless apart from the shared ledger, a monotonic
 * counter read by difference only (FrankenPHP worker mode, kernel reset off).
 */
final readonly class CookieConsentConflictListener
{
    /** kernel.exception priority: after the application's own listeners, before the framework's error listener (-128). */
    public const PRIORITY = -64;

    /** kernel.request priority: before the router (32) and the firewall (8), so nothing of the request wrote yet. */
    public const REQUEST_PRIORITY = 2048;

    /** Request attribute holding the bundle's own commit mark. */
    public const MARK_ATTRIBUTE = '_nowo_cookie_consent_commit_mark';

    /** Response header carrying a domain error code (never a conflict code). */
    public const ERROR_CODE_HEADER = 'X-Error-Code';

    /** Domain code of an admin save refused by a unique key (concurrent edit, or a cookie name already used). */
    public const DUPLICATE_KEY = 'COOKIE_CONSENT_DUPLICATE_KEY';

    /** Message of a CONFLICT_001 answer (nothing committed). */
    public const RETRY_SAFE_MESSAGE = 'Another change is being saved at the same time. Retry in a moment.';

    /** Message of a CONFLICT_002 answer (part of the request may be committed). */
    public const COMMITTED_MESSAGE = 'Part of the change may already be saved. Reload the page before trying again.';

    /** Message of the duplicate-key answer. */
    public const DUPLICATE_KEY_MESSAGE = 'This cookie name or translation is already used, possibly by a change saved at the same time. Reload the page and apply your change again.';

    /** Controller namespace of the bundle. */
    private const CONTROLLER_PREFIX = 'Nowo\\CookieConsentBundle\\Controller\\';

    /** Admin controllers: the only bundle routes that write rows with a unique key. */
    private const ADMIN_CONTROLLERS = [
        self::CONTROLLER_PREFIX . 'CookieDefinitionAdminController',
        self::CONTROLLER_PREFIX . 'CookieConsentConfigSettingsAdminController',
    ];

    private ConflictResponsePolicy $policy;

    private TransientFailureClassifier $classifier;

    /**
     * @param ConflictResponsePolicy|null $policy the conflict status and default Retry-After (null = 409, 1 s)
     * @param CommitLedger|null $ledger real commits counted by the commit-tracking middleware (null = none)
     * @param RequestStack|null $requestStack reads the main request's mark from a sub-request
     * @param ManagerRegistry|null $registry the registry of the bundle's entity manager (to replace it once closed)
     * @param string|null $managerName `doctrine.entity_manager`; null = the manager that maps the bundle entities
     */
    public function __construct(
        ?ConflictResponsePolicy $policy = null,
        private ?CommitLedger $ledger = null,
        private ?RequestStack $requestStack = null,
        private ?ManagerRegistry $registry = null,
        private ?string $managerName = null,
    ) {
        $this->policy     = $policy ?? new ConflictResponsePolicy();
        $this->classifier = new TransientFailureClassifier();
    }

    /**
     * Takes the bundle's commit mark on the main request.
     */
    public function onRequest(RequestEvent $event): void
    {
        if ($this->ledger instanceof CommitLedger && $event->isMainRequest()) {
            $event->getRequest()->attributes->set(self::MARK_ATTRIBUTE, new CommitMark($this->ledger));
        }
    }

    /**
     * Maps a failure of a bundle controller: lock conflict → 409 + code, admin unique violation → domain 409.
     */
    public function onException(ExceptionEvent $event): void
    {
        $request    = $event->getRequest();
        $controller = $this->bundleController($request);
        if ($controller === null) {
            return;
        }

        $failure = $event->getThrowable();
        $this->replaceClosedManager();

        if ($this->isLockConflict($failure)) {
            $event->setThrowable($this->httpExceptionFor($request, $failure));

            return;
        }

        if (in_array($controller, self::ADMIN_CONTROLLERS, true) && $this->isUniqueViolation($failure)) {
            $event->setThrowable(new ConflictHttpException(self::DUPLICATE_KEY_MESSAGE, $failure, 0, [self::ERROR_CODE_HEADER => self::DUPLICATE_KEY]));
        }
    }

    /**
     * Whether the failure is a lock conflict (deadlock, lock wait timeout, serialization failure, lost savepoint).
     *
     * Unique violations and lost connections are not.
     */
    public function isLockConflict(Throwable $failure): bool
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                return true;
            }
        }

        return $this->classifier->isTransient($failure) || $this->classifier->isCommitOutcomeUnknown($failure);
    }

    /**
     * The conflict answer of a lock conflict of the request: 409 (or 503) with the code, and Retry-After for 001.
     */
    public function httpExceptionFor(Request $request, Throwable $failure): HttpException
    {
        $code = $this->code($request, $failure);

        return $this->policy->httpExceptionFor(
            $code,
            $code->retrySafe() ? self::RETRY_SAFE_MESSAGE : self::COMMITTED_MESSAGE,
            $failure,
            $this->hint($failure),
        );
    }

    /**
     * CONFLICT_002 when transaction-doctrine's request mark, or the bundle's own one, saw a commit; else the failure decides.
     */
    public function code(Request $request, Throwable $failure): ConflictCode
    {
        $code = ConflictCode::forRequest($request, $failure);
        if ($code->retrySafe() && $this->ownMark($request)?->committedSince() === true) {
            return ConflictCode::Committed;
        }

        return $code;
    }

    private function ownMark(Request $request): ?CommitMark
    {
        $mark = $request->attributes->get(self::MARK_ATTRIBUTE) ?? $this->requestStack?->getMainRequest()?->attributes->get(self::MARK_ATTRIBUTE);

        return $mark instanceof CommitMark ? $mark : null;
    }

    /** The first Retry-After hint of the chain (a transient failure of a runner); null for none or zero. */
    private function hint(Throwable $failure): ?int
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                $hint = $current->retryAfterSeconds();

                return $hint !== null && $hint > 0 ? $hint : null;
            }
        }

        return null;
    }

    private function isUniqueViolation(Throwable $failure): bool
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof UniqueConstraintViolationException) {
                return true;
            }
        }

        return false;
    }

    /**
     * Replaces the bundle's entity manager when a failed flush closed it (never an open one, REQ-RUNTIME-003 rule 2).
     */
    private function replaceClosedManager(): void
    {
        $manager = $this->registry?->getManagerForClass(CookieConsentConfig::class);
        if (!$this->registry instanceof ManagerRegistry || !$manager instanceof EntityManagerInterface || $manager->isOpen()) {
            return;
        }

        $name = $this->managerName;
        if ($name === null) {
            foreach (array_keys($this->registry->getManagerNames()) as $candidate) {
                if ($this->registry->getManager($candidate) === $manager) {
                    $name = $candidate;

                    break;
                }
            }
        }

        if ($name !== null) {
            $this->registry->resetManager($name);
        }
    }

    /**
     * The controller class when the request is handled by a controller of the bundle, else null.
     */
    private function bundleController(Request $request): ?string
    {
        $controller = $request->attributes->get('_controller');
        if (!is_string($controller) || !str_starts_with($controller, self::CONTROLLER_PREFIX)) {
            return null;
        }

        $class = strstr($controller, '::', true);

        return $class === false ? $controller : $class;
    }
}
