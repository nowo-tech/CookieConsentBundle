<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Cookie;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\CookieConsentBundle\Entity\CookieConsentLog;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

use function strlen;

/**
 * Persists anonymized cookie consent choices to the database.
 */
class CookieLogger
{
    private readonly ?Request $request;

    /**
     * Creates a new cookie consent logger.
     *
     * @param EntityManagerInterface $entityManager The Doctrine entity manager
     * @param RequestStack $requestStack The HTTP request stack
     * @param ClockInterface $clock Clock used for consent timestamps
     * @param LoggerInterface|null $logger Optional PSR logger (never receives raw IP)
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        RequestStack $requestStack,
        private readonly ClockInterface $clock,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->request = $requestStack->getMainRequest();
    }

    /**
     * Logs consent category choices for the current request.
     *
     * @param array<string, bool|string> $categories The submitted category values
     * @param string $key The anonymous consent key
     *
     * @return void
     */
    public function log(array $categories, string $key): void
    {
        if (!$this->request instanceof Request) {
            throw new RuntimeException('No request found');
        }

        $ip               = $this->anonymizeIp($this->request->getClientIp());
        $loggedCategories = [];

        foreach ($categories as $category => $value) {
            if ($category === 'required') {
                continue;
            }

            $loggedCategories[] = (string) $category;
            $boolValue          = $value === true || $value === 'true';
            $this->persistCookieConsentLog((string) $category, $boolValue, $ip, $key);
        }

        $this->entityManager->flush();

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

        $this->entityManager->persist($cookieConsentLog);
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
