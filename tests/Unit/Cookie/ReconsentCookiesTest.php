<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Unit\Cookie;

use DateTimeImmutable;
use Nowo\CookieConsentBundle\Clock\SystemClock;
use Nowo\CookieConsentBundle\Config\ResolvedCookieConsentConfig;
use Nowo\CookieConsentBundle\Cookie\CookieChecker;
use Nowo\CookieConsentBundle\Cookie\CookieHandler;
use Nowo\CookieConsentBundle\Cookie\CookieLogger;
use Nowo\CookieConsentBundle\Entity\CookieConsentConfig;
use Nowo\CookieConsentBundle\Enum\CookieNameEnum;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentFormSubscriber;
use Nowo\CookieConsentBundle\EventSubscriber\CookieConsentOutdatedConsentSubscriber;
use Nowo\CookieConsentBundle\Tests\Support\SqliteEntityManagerFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\CacheAttributeListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Owner decision JH-L1 (1.25): an outdated consent is expired in the browser too (except the consent key), never on
 * a response a shared cache may store, and the consent log records the profile revision the visitor answered.
 */
final class ReconsentCookiesTest extends TestCase
{
    public function testClearExpiresConsentCategoryAndGranularCookiesButKeepsTheKey(): void
    {
        $request = $this->outdatedRequest();
        $request->cookies->set('PHPSESSID', 'abc');
        $response = new Response();

        (new CookieHandler(true, new SystemClock()))->clear($request, $response);

        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie;
        }

        self::assertSame([
            CookieNameEnum::COOKIE_CONSENT_NAME,
            CookieNameEnum::getCookieCategoryName('analytics'),
            CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME,
        ], array_keys($cookies));
        foreach ($cookies as $cookie) {
            self::assertTrue($cookie->isCleared(), $cookie->getName());
            self::assertSame('/', $cookie->getPath());
            self::assertTrue($cookie->isSecure());
            self::assertTrue($cookie->isHttpOnly());
            self::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        }
    }

    public function testSubscriberExpiresAnOutdatedConsentWhenNothingIsSubmitted(): void
    {
        $request  = $this->outdatedRequest();
        $response = new Response();

        $this->subscriber($request, submitted: false)->dispatch($this->event($request, $response), KernelEvents::RESPONSE);

        $names = array_map(static fn (Cookie $cookie): string => $cookie->getName(), $response->headers->getCookies());
        self::assertContains(CookieNameEnum::COOKIE_CONSENT_NAME, $names);
        self::assertNotContains(CookieNameEnum::COOKIE_CONSENT_KEY_NAME, $names);
    }

    public function testSubscriberNeverExpiresOnAPublicResponse(): void
    {
        $request  = $this->outdatedRequest();
        $response = new Response();
        $response->setPublic();
        $response->setMaxAge(300);

        $this->subscriber($request, submitted: false)->dispatch($this->event($request, $response), KernelEvents::RESPONSE);

        self::assertSame([], $response->headers->getCookies());
    }

    /**
     * Audit JR-L1: `#[Cache(public: true)]` is applied by Symfony's CacheAttributeListener at priority -10; the
     * expiry runs after it, so such responses never carry the `Set-Cookie`.
     *
     * @return iterable<string, array{Cache}>
     */
    public static function cacheAttributes(): iterable
    {
        yield 'public' => [new Cache(maxage: 600, public: true)];
        yield 's-maxage only' => [new Cache(smaxage: 600)];
    }

    #[DataProvider('cacheAttributes')]
    public function testSubscriberNeverExpiresOnAResponseMadePublicByTheCacheAttribute(Cache $cache): void
    {
        $request = $this->outdatedRequest();
        $request->attributes->set('_cache', [$cache]);
        $response = new Response();

        $this->subscriber($request, submitted: false)->dispatch($this->event($request, $response), KernelEvents::RESPONSE);

        self::assertTrue($response->headers->hasCacheControlDirective('public') || $response->headers->hasCacheControlDirective('s-maxage'));
        self::assertSame([], $response->headers->getCookies());
    }

    public function testExpiryRunsAfterTheCacheAttributeListener(): void
    {
        $priority = CookieConsentOutdatedConsentSubscriber::getSubscribedEvents()[KernelEvents::RESPONSE][1];

        self::assertSame(['onKernelResponse', -10], CacheAttributeListener::getSubscribedEvents()[KernelEvents::RESPONSE]);
        self::assertLessThan(-10, $priority);
        self::assertGreaterThan(-1000, $priority, 'before the session listener');
    }

    public function testSubscriberLeavesAValidConsentAlone(): void
    {
        $request = $this->outdatedRequest();
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_NAME, gmdate('Y-m-d\TH:i:s\Z', time() + 60));
        $response = new Response();

        $this->subscriber($request, submitted: false)->dispatch($this->event($request, $response), KernelEvents::RESPONSE);

        self::assertSame([], $response->headers->getCookies());
    }

    public function testSubscriberSavesInsteadOfExpiringWhenTheVisitorAnswers(): void
    {
        $request  = $this->outdatedRequest();
        $response = new Response();

        $this->subscriber($request, submitted: true)->dispatch($this->event($request, $response), KernelEvents::RESPONSE);

        $consent = null;
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === CookieNameEnum::COOKIE_CONSENT_NAME) {
                $consent = $cookie;
            }
        }
        self::assertNotNull($consent);
        self::assertFalse($consent->isCleared(), 'fresh consent written, not expired');
        self::assertGreaterThanOrEqual(time() - 5, (new DateTimeImmutable((string) $consent->getValue()))->getTimestamp());
    }

    public function testLoggerRecordsTheProfileRevision(): void
    {
        $request = $this->outdatedRequest();
        $stack   = new RequestStack();
        $stack->push($request);

        $em = SqliteEntityManagerFactory::create();

        (new CookieLogger($em, $stack, new SystemClock()))->log(['analytics' => true], 'key');

        $plain = new RequestStack();
        $plain->push(Request::create('/'));
        (new CookieLogger($em, $plain, new SystemClock()))->log(['analytics' => true], 'key');

        $revisions = $em->getConnection()->fetchFirstColumn('SELECT consent_revision FROM dashboard_cookie_log ORDER BY id');
        self::assertSame([5, null], array_map(static fn (mixed $revision): ?int => $revision === null ? null : (int) $revision, $revisions), 'second: no database profile');
    }

    private function outdatedRequest(): Request
    {
        $config = (new CookieConsentConfig())->setRevision(5)->requireReconsent(new DateTimeImmutable('2026-01-01T00:00:00Z'));

        $request = Request::create('/');
        $request->attributes->set('nowo_cookie_consent_config', new ResolvedCookieConsentConfig($config, null));
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_NAME, '2025-12-31T23:59:59Z');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_KEY_NAME, 'key');
        $request->cookies->set(CookieNameEnum::getCookieCategoryName('analytics'), 'true');
        $request->cookies->set(CookieNameEnum::COOKIE_CONSENT_GRANULAR_NAME, '{"_ga":true}');

        return $request;
    }

    private function subscriber(Request $request, bool $submitted): EventDispatcher
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn($submitted);
        $form->method('isValid')->willReturn($submitted);
        $form->method('getData')->willReturn(['analytics' => true]);

        $factory = $this->createMock(FormFactoryInterface::class);
        $factory->method('create')->willReturn($form);

        $stack = new RequestStack();
        $stack->push($request);

        $formSubscriber     = new CookieConsentFormSubscriber($factory, $this->createMock(CookieLogger::class), new CookieHandler(true, new SystemClock()), false);
        $outdatedSubscriber = new CookieConsentOutdatedConsentSubscriber(new CookieChecker($stack), new CookieHandler(true, new SystemClock()));

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($formSubscriber);
        $dispatcher->addSubscriber($outdatedSubscriber);
        $dispatcher->addSubscriber(new CacheAttributeListener());

        return $dispatcher;
    }

    private function event(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
