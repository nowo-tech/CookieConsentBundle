<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

use function array_keys;
use function strlen;

/**
 * Persists anonymized cookie consent choices to the database.
 *
 * The request and the entity manager are resolved on every {@see self::log()} call: the
 * service is shared and may outlive a single request (FrankenPHP worker mode).
 */
class CookieLogger
{
    /** @var list<CookieConsentLog> Entities persisted by the current {@see self::log()} call */
    private array $pendingLogs = [];

    /**
     * Creates a new cookie consent logger.
     *
     * @param EntityManagerInterface $entityManager The Doctrine entity manager (fallback when no registry is given)
     * @param RequestStack $requestStack The HTTP request stack
     * @param ClockInterface $clock Clock used for consent timestamps
     * @param LoggerInterface|null $logger Optional PSR logger (never receives raw IP)
     * @param ManagerRegistry|null $managerRegistry Used to obtain (and reset when closed) the manager of {@see CookieConsentLog}
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly ClockInterface $clock,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ManagerRegistry $managerRegistry = null,
    ) {
    }

    /**
     * Logs consent category choices for the current request.
     *
     * @param array<string, bool|string> $categories The submitted category values
     * @param string $key The anonymous consent key
     */
    public function log(array $categories, string $key): void
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request instanceof Request) {
            throw new RuntimeException('No request found');
        }

        $ip               = $this->anonymizeIp($request->getClientIp());
        $loggedCategories = [];
        $entityManager    = $this->getEntityManager();

        try {
            foreach ($categories as $category => $value) {
                if ($category === 'required') {
                    continue;
                }

                $loggedCategories[] = (string) $category;
                $boolValue          = $value === true || $value === 'true';
                $this->persistCookieConsentLog((string) $category, $boolValue, $ip, $key);
            }

            $entityManager->flush();

            foreach ($this->pendingLogs as $cookieConsentLog) {
                $entityManager->detach($cookieConsentLog);
            }
        } catch (Throwable $exception) {
            $this->getEntityManager();

            throw $exception;
        } finally {
            $this->pendingLogs = [];
        }

        $this->logger?->info('Cookie consent choices persisted.', [
            'consent_key' => $key,
            'categories'  => $loggedCategories,
        ]);
    }

    protected function persistCookieConsentLog(string $category, bool $value, string $ip, string $key): void
    {
        $cookieConsentLog = (new CookieConsentLog())
            ->setIpAddress($ip)
            ->setCookieConsentKey($key)
            ->setCookieName($category)
            ->setCookieValue($value)
            ->setTimestamp($this->clock->now());

        $this->getEntityManager()->persist($cookieConsentLog);
        $this->pendingLogs[] = $cookieConsentLog;
    }

    /**
     * Returns an open entity manager for {@see CookieConsentLog}, resetting it when a previous failure closed it.
     */
    private function getEntityManager(): EntityManagerInterface
    {
        if (!$this->managerRegistry instanceof ManagerRegistry) {
            return $this->entityManager;
        }

        $manager = $this->managerRegistry->getManagerForClass(CookieConsentLog::class);

        if (!$manager instanceof EntityManagerInterface) {
            return $this->entityManager;
        }

        if ($manager->isOpen()) {
            return $manager;
        }

        foreach (array_keys($this->managerRegistry->getManagerNames()) as $name) {
            if ($this->managerRegistry->getManager($name) === $manager) {
                $this->managerRegistry->resetManager($name);

                break;
            }
        }

        $manager = $this->managerRegistry->getManagerForClass(CookieConsentLog::class);

        return $manager instanceof EntityManagerInterface ? $manager : $this->entityManager;
    }

    protected function anonymizeIp(?string $ip): string
    {
        if ($ip === null) {
            return 'unknown';
        }

        $lastDot = strrpos($ip, '.');
        if ($lastDot === false) {
            return $ip;
        }

        ++$lastDot;

        return substr($ip, 0, $lastDot) . str_repeat('x', strlen($ip) - $lastDot);
    }
}
