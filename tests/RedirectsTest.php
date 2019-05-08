<?php

namespace Statamic\Addons\Redirects\tests;

use Illuminate\Foundation\Testing\TestCase;
use Statamic\Addons\Redirects\AutoRedirect;
use Statamic\Addons\Redirects\AutoRedirectsManager;
use Statamic\Addons\Redirects\ManualRedirect;
use Statamic\Addons\Redirects\ManualRedirectsManager;
use Statamic\Addons\Redirects\RedirectsLogger;
use Statamic\Addons\Redirects\RedirectsProcessor;
use Statamic\API\Page;

/**
 * @group redirects
 *
 * Functional tests for the redirects Addon.
 * Note: We cannot extend Statamic's TestCase, as we rely on the real event system.
 */
class RedirectsTest extends TestCase
{
    /**
     * @var string
     */
    private $storagePath;

    /**
     * @var AutoRedirectsManager
     */
    private $autoRedirectsManager;

    /**
     * @var ManualRedirectsManager
     */
    private $manualRedirectsManager;

    /**
     * @var RedirectsLogger
     */
    private $redirectsLogger;

    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';

    public function setUp()
    {
        parent::setUp();

        $this->storagePath = __DIR__ . '/temp/';
        $this->redirectsLogger = new RedirectsLogger($this->storagePath);
        $this->autoRedirectsManager = new AutoRedirectsManager($this->storagePath . 'auto.yaml', $this->redirectsLogger);
        $this->manualRedirectsManager = new ManualRedirectsManager($this->storagePath . 'manual.yaml', $this->redirectsLogger);

        // Swap our services in Laravel's container.
        $this->app->singleton(RedirectsLogger::class, function () {
            return $this->redirectsLogger;
        });

        $this->app->singleton(RedirectsProcessor::class, function () {
            return new RedirectsProcessor($this->manualRedirectsManager, $this->autoRedirectsManager, $this->redirectsLogger);
        });
    }

    public function test_auto_redirect()
    {
        $redirect = (new AutoRedirect())
            ->setFromUrl('/not-existing-source-auto')
            ->setToUrl('/target')
            ->setContentId('1234');

        $this->autoRedirectsManager
            ->add($redirect)
            ->flush();

        $this->get('/not-existing-source-auto');

        $this->assertRedirectedTo('/target');
        $this->assertEquals(['/not-existing-source-auto' => 1], $this->redirectsLogger->getAutoRedirects());
    }

    public function test_manual_redirect()
    {
        $redirect = (new ManualRedirect())
            ->setFrom('/not-existing-source-manual')
            ->setTo('/target');

        $this->manualRedirectsManager
            ->add($redirect)
            ->flush();

        $this->get('/not-existing-source-manual');

        $this->assertRedirectedTo('/target');
        $this->assertEquals(['/not-existing-source-manual' => 1], $this->redirectsLogger->getManualRedirects());
    }

    public function test_manual_redirect_with_placeholders()
    {
        $redirect = (new ManualRedirect())
            ->setFrom('/news/{year}/{month}/{slug}')
            ->setTo('/blog/{month}/{year}/{slug}');

        $this->manualRedirectsManager
            ->add($redirect)
            ->flush();

        $this->get('/news/2019/01/some-sluggy-slug');

        $this->assertRedirectedTo('/blog/01/2019/some-sluggy-slug');
    }

    public function test_manual_redirect_to_content()
    {
        /** @var \Statamic\Contracts\Data\Pages\Page $page */
        $page = Page::create('/foo')
            ->order(2)
            ->published(true)
            ->get()
            ->save();

        $redirect = (new ManualRedirect())
            ->setFrom('/not-existing-source')
            ->setTo($page->id());

        $this->manualRedirectsManager
            ->add($redirect)
            ->flush();

        $this->get('/not-existing-source');

        $this->assertRedirectedTo($page->url());

        $page->delete();
    }

    /**
     * @dataProvider timedActivationDataProvider
     */
    public function test_manual_redirect_timed_activation($start, $end, $shouldRedirect)
    {
        $redirect = (new ManualRedirect())
            ->setFrom('/not-existing-source')
            ->setTo('/target')
            ->setStartDate($start ? new \DateTime(date('Y-m-d H:i:s', $start)) : null)
            ->setEndDate($end ? new \DateTime(date('Y-m-d H:i:s', $end)) : null);

        $this->manualRedirectsManager
            ->add($redirect)
            ->flush();

        $this->get('/not-existing-source');

        if ($shouldRedirect) {
            $this->assertRedirectedTo('/target');
            if ($start && $end) {
                $this->assertEquals(302, $this->response->getStatusCode());
            }
        } else {
            $this->assertResponseStatus(404);
        }
    }

    public function timedActivationDataProvider()
    {
        return [
            [time(), null, true],
            [null, strtotime('+1 minute'), true],
            [time(), strtotime('+1 minute'), true],
            [strtotime('-1 minute'), strtotime('+1 minute'), true],
            [strtotime('+1 hour'), null, false],
            [null, strtotime('-1 minute'), false],
            [strtotime('-1 minute'), strtotime('-1 minute'), false],
            [strtotime('+1 minute'), strtotime('+1 minute'), false],
        ];
    }

    /**
     * @dataProvider queryStringsDataProvider
     */
    public function test_manual_redirect_retain_query_strings($shouldRetainQueryStrings, $queryStrings)
    {
        $redirect = (new ManualRedirect())
            ->setFrom('/not-existing-source')
            ->setTo('/target')
            ->setRetainQueryStrings($shouldRetainQueryStrings);

        $this->manualRedirectsManager
            ->add($redirect)
            ->flush();

        $this->get('/not-existing-source' . $queryStrings);

        $hasQueryStringsAtTargetUrl = strpos($this->response->getTargetUrl(), $queryStrings) !== false;

        if ($shouldRetainQueryStrings) {
            $this->assertTrue($hasQueryStringsAtTargetUrl);
        } else {
            $this->assertFalse($hasQueryStringsAtTargetUrl);
        }
    }

    public function queryStringsDataProvider()
    {
        return [
            [false, '?foo=bar'],
            [true, '?foo=bar'],
        ];
    }

    /**
     * @dataProvider statusCodeDataProvider
     */
    public function test_manual_redirect_status_codes($statusCode)
    {
        $redirect = (new ManualRedirect())
            ->setFrom('/not-existing-source')
            ->setTo('/target')
            ->setStatusCode($statusCode);

        $this->manualRedirectsManager
            ->add($redirect)
            ->flush();

        $this->get('/not-existing-source');

        $this->assertEquals($statusCode, $this->response->getStatusCode());
    }

    public function statusCodeDataProvider()
    {
        return [
            [301], [302],
        ];
    }

    /**
     * @dataProvider localesDataProvider
     */
    public function test_manual_redirect_locale($redirectLocale, $locale, $shouldRedirect)
    {
        $redirect = (new ManualRedirect())
            ->setFrom('/not-existing-source')
            ->setTo('/target')
            ->setLocale($redirectLocale);

        $this->manualRedirectsManager
            ->add($redirect)
            ->flush();

        $currentLocale = site_locale();
        if ($locale) {
            site_locale($locale);
        }

        $this->get('/not-existing-source');

        if ($shouldRedirect) {
            $this->assertRedirectedTo('/target');
        } else {
            $this->assertResponseStatus(404);
        }

        site_locale($currentLocale);
    }

    public function localesDataProvider()
    {
        return [
            [null, null, true],
            [null, 'de', true],
            ['en', 'en', true],
            ['en', 'de', false],
            ['de', 'en', false],
        ];
    }

    public function test_log_404()
    {
        $this->get('/not-existing-source');

        $this->assertResponseStatus(404);

        $logs = $this->redirectsLogger->get404s();

        $this->assertEquals(['/not-existing-source' => 1], $logs);
    }

    public function createApplication()
    {
        $app = require statamic_path('/bootstrap') . '/app.php';

        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

        return $app;
    }

    public function tearDown()
    {
        parent::tearDown();

        @unlink($this->storagePath . 'auto.yaml');
        @unlink($this->storagePath . 'manual.yaml');
        @unlink($this->storagePath . 'log_auto.yaml');
        @unlink($this->storagePath . 'log_manual.yaml');
        @unlink($this->storagePath . 'log_404.yaml');
    }
}
