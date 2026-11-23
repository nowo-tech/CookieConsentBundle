<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\DependencyInjection\Compiler\CookieConsentHostFlushNoticePass;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentPrivateManagerBoundarySubscriber;
use Nowo\TransactionDoctrine\HostFlushProbe;
use Nowo\TransactionDoctrine\PrivateManager\PrivateEntityManager;
use Symfony\Contracts\Service\ResetInterface;

/**
 * 1.35 (REQ-ARCH-001 rule 6 deprecation layer): reports the application's pending changes that a profile or cookie
 * definition save flushes on the application's manager (2.0 always writes through a private entity manager and no
 * longer flushes them).
 *
 * Registered by {@see CookieConsentHostFlushNoticePass} only when the bundle writes through the application's default
 * manager: `doctrine.private_entity_manager` unset or `false`, and `doctrine.entity_manager` unset or naming the
 * default manager. A dedicated `doctrine.entity_manager` is the bundle's own and is never probed; with
 * `private_entity_manager: true` the reporter does not exist. Called right before each admin write flushes:
 * `CookieConsentConfigRepository::saveSettings()` (profile settings), `CookieDefinitionRepository::saveEdit()` (cookie
 * definition edit) and the create / delete actions of `CookieDefinitionAdminController`.
 *
 * For each entity class outside `Nowo\CookieConsentBundle\Entity` with pending work in that manager, the host-flush
 * probe of nowo-tech/transaction-doctrine ({@see HostFlushProbe}) triggers one `E_USER_DEPRECATED` per class and unit
 * of work: "Your pending changes to App\X were flushed by NowoCookieConsentBundle; 2.0 no longer does." The bundle
 * never changes an application entity itself, so every class reported is the application's own work.
 *
 * A {@see PrivateEntityManager} is never inspected (a host that passes one to a repository save). The probe never
 * throws (a failed inspection is logged as a warning or swallowed), so it never breaks the flush it observes. Read-only: it never flushes, clears, closes
 * or resets a manager (REQ-RUNTIME-003). The probe is the shared `nowo_transaction_doctrine.host_flush_probe` when the
 * application registers NowoTransactionDoctrineBundle, else one of its own. Its "already reported" classes are reset
 * at every outermost unit-of-work boundary (request, Messenger message, console command:
 * {@see CookieConsentPrivateManagerBoundarySubscriber}) and at `kernel.reset`, so a FrankenPHP worker with the kernel
 * reset off starts every unit clean.
 *
 * @internal registered by the bundle; not an extension point
 */
final readonly class CookieConsentHostFlushReporter implements ResetInterface
{
    /** The bundle named in the deprecation. */
    public const BUNDLE_NAME = 'NowoCookieConsentBundle';

    /** The bundle's entity namespace: its classes are never reported. */
    public const OWN_NAMESPACES = ['Nowo\\CookieConsentBundle\\Entity'];

    private HostFlushProbe $probe;

    /**
     * @param HostFlushProbe|null $probe transaction-doctrine's shared probe (null: one of its own)
     */
    public function __construct(?HostFlushProbe $probe = null)
    {
        $this->probe = $probe ?? new HostFlushProbe();
    }

    /**
     * Right before the bundle flushes `$entityManager`: reports the application's pending changes it would write.
     */
    public function beforeFlush(EntityManagerInterface $entityManager): void
    {
        if ($entityManager instanceof PrivateEntityManager) {
            return;
        }

        // Never throws (transaction-doctrine >= 1.9.1, audit XA-3): a failed inspection is logged or swallowed by the probe.
        $this->probe->beforeHostFlush($entityManager, self::BUNDLE_NAME, self::OWN_NAMESPACES);
    }

    /** A new unit of work (request, message, command) starts: nothing was reported yet. */
    public function reset(): void
    {
        $this->probe->reset();
    }
}
