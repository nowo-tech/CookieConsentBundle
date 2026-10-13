<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Tests\Support;

use Nowo\CookieConsentBundle\Admin\CookieConsentConfigSettingsSection;
use Nowo\CookieConsentBundle\Twig\CookieConsentAdminTwigExtension;
use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

use function dirname;
use function http_build_query;
use function is_array;
use function is_string;
use function preg_replace;
use function strtr;
use function trim;

/**
 * Renders the admin Web UI pages with a minimal Twig stack (UiKit macros, Symfony form theme, stub
 * router/translator) so tests can assert the chrome hooks and compare default output with goldens.
 */
final class AdminTemplateRenderer
{
    private const LAYOUT = '@Test/layout.html.twig';

    public function __construct(
        private readonly string $viewsDir = '',
    ) {
    }

    /**
     * @param array<string, mixed> $chrome Resolved chrome (AdminChrome::resolve()); empty = defaults
     * @param array<string, string> $templates Extra in-memory host templates (name => source), e.g. '@Test/x.html.twig'
     * @param array<string, mixed> $globals Extra Twig globals (e.g. nowo_ui_kit_row_actions_display)
     */
    public function twig(array $chrome = [], string $cssFramework = 'bootstrap5', array $templates = [], string $route = 'nowo_cookie_consent_cookie_definitions_index', array $globals = []): Environment
    {
        $root  = dirname(__DIR__, 2);
        $views = $this->viewsDir !== '' ? $this->viewsDir : $root . '/src/Resources/views';

        $loader = new FilesystemLoader([$root . '/vendor/symfony/twig-bridge/Resources/views/Form']);
        $loader->addPath($views, 'NowoCookieConsentBundle');
        $loader->addPath($views, '!NowoCookieConsentBundle');
        $loader->addPath($root . '/vendor/nowo-tech/ui-kit-bundle/src/Resources/views', 'NowoUiKitBundle');

        $array = new ArrayLoader($templates + [
            self::LAYOUT => '<main>{% block stylesheets %}{% endblock %}{% block nowo_ui_flashes %}{% endblock %}<title>{% block title %}{% endblock %}</title>{% block body %}{% endblock %}{% block javascripts %}{% endblock %}</main>',
        ]);
        $chain = new ChainLoader([$array, $loader]);

        $twig = new Environment($chain, ['strict_variables' => true, 'cache' => false]);

        $request = Request::create('/cookie-consent-config/1/cookies');
        $request->attributes->set('_route', $route);
        $request->attributes->set('_route_params', ['configId' => 1]);
        $stack = new RequestStack();
        $stack->push($request);
        $app = new AppVariable();
        $app->setRequestStack($stack);
        $twig->addGlobal('app', $app);
        foreach ($globals as $name => $value) {
            $twig->addGlobal($name, $value);
        }

        $twig->addExtension(new CookieConsentAdminTwigExtension(self::LAYOUT, $cssFramework, 'bootstrap-icons', $chrome));
        $twig->addExtension(new FormExtension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRenderer => new FormRenderer(new TwigRendererEngine(['form_div_layout.html.twig'], $twig)),
        ]));

        $catalog = Yaml::parseFile($root . '/src/Resources/translations/NowoCookieConsentBundle.en.yaml');
        $twig->addFilter(new TwigFilter('trans', static function (mixed $id, array $params = [], ?string $domain = null) use ($catalog): string {
            $id = is_string($id) ? $id : '';
            if ($domain === 'NowoCookieConsentBundle' && is_array($catalog) && is_string($catalog[$id] ?? null)) {
                $id = $catalog[$id];
            }
            $replace = [];
            foreach ($params as $key => $value) {
                $replace[(string) $key] = is_array($value) ? '' : (string) $value;
            }

            return strtr($id, $replace);
        }));
        $twig->addFunction(new TwigFunction('path', static fn (string $name, array $params = []): string => '/' . $name . ($params !== [] ? '?' . http_build_query($params) : '')));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path, ?string $package = null): string => '/assets/' . ($package !== null ? $package . '/' : '') . $path));

        return $twig;
    }

    /**
     * @param array<string, mixed> $chrome
     * @param array<string, string> $templates
     * @param array<string, mixed> $globals
     */
    public function index(bool $empty = false, array $chrome = [], int $pages = 2, array $templates = [], string $page = '@NowoCookieConsentBundle/admin/cookie_definition/index.html.twig', array $globals = []): string
    {
        $definitions = [];
        $deleteForms = [];
        foreach ($empty ? [] : [7 => '_ga', 8 => 'session'] as $id => $name) {
            $definitions[]    = $this->definition($id, $name);
            $deleteForms[$id] = $this->tokenForm('delete_' . $id, '/nowo_cookie_consent_cookie_definitions_delete?id=' . $id);
        }

        return $this->twig($chrome, 'bootstrap5', $templates, 'nowo_cookie_consent_cookie_definitions_index', $globals)->render($page, [
            'config'       => $this->config(),
            'definitions'  => $definitions,
            'delete_forms' => $deleteForms,
            'page'         => 1,
            'pages'        => $pages,
            'total'        => 2,
            'pageSize'     => 20,
        ]);
    }

    /**
     * @param array<string, mixed> $chrome
     * @param array<string, string> $templates
     */
    public function form(array $chrome = [], array $templates = []): string
    {
        $form = $this->factory()->createNamedBuilder('cookie_definition', FormType::class)
            ->add('name', TextType::class)
            ->add('_token', HiddenType::class, ['data' => 'tok'])
            ->getForm()
            ->createView();

        return $this->twig($chrome, 'bootstrap5', $templates, 'nowo_cookie_consent_cookie_definitions_edit')
            ->render('@NowoCookieConsentBundle/admin/cookie_definition/form.html.twig', [
                'config'     => $this->config(),
                'definition' => $this->definition(7, '_ga'),
                'form'       => $form,
                'isNew'      => false,
            ]);
    }

    /**
     * @param array<string, mixed> $chrome
     * @param array<string, string> $templates
     */
    public function settings(array $chrome = [], array $templates = []): string
    {
        $form = $this->factory()->createNamedBuilder('settings', FormType::class)
            ->add('displayName', TextType::class)
            ->add('_token', HiddenType::class, ['data' => 'tok'])
            ->getForm()
            ->createView();

        return $this->twig($chrome, 'bootstrap5', $templates, 'nowo_cookie_consent_config_settings_section')
            ->render('@NowoCookieConsentBundle/admin/config/settings.html.twig', [
                'config'   => $this->config(),
                'form'     => $form,
                'section'  => CookieConsentConfigSettingsSection::Behavior,
                'sections' => CookieConsentConfigSettingsSection::cases(),
            ]);
    }

    /**
     * Collapses insignificant whitespace so goldens compare DOM-equivalent markup.
     */
    public static function normalize(string $html): string
    {
        $html = (string) preg_replace('/>\s+</', '><', $html);
        $html = (string) preg_replace('/\s+/', ' ', $html);
        $html = (string) preg_replace('/\s+>/', '>', $html);

        return trim($html);
    }

    private function tokenForm(string $name, string $action): FormView
    {
        return $this->factory()->createNamedBuilder($name, FormType::class, null, ['action' => $action])
            ->add('_token', HiddenType::class, ['data' => 'tok'])
            ->getForm()
            ->createView();
    }

    private function factory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->getFormFactory();
    }

    private function config(): object
    {
        return new class {
            public int $id = 1;

            public string $displayName = 'Default profile';
        };
    }

    private function definition(int $id, string $name): object
    {
        $definition = new class {
            public int $id = 0;

            public string $name = '';

            public string $category = 'analytics';

            public string $duration = '1 year';

            public bool $allowedByDefault = false;

            public function findTranslation(string $locale): ?object
            {
                return $locale === 'en' ? new class {
                    public string $provider = 'Acme';

                    public string $purpose = 'Measures traffic';
                } : null;
            }
        };
        $definition->id   = $id;
        $definition->name = $name;

        return $definition;
    }
}
