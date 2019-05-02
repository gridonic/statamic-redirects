<?php

namespace Statamic\Addons\Redirects\tests;

use Statamic\Addons\Redirects\AutoRedirect;
use Statamic\Addons\Redirects\AutoRedirectsManager;
use Statamic\Addons\Redirects\RedirectsLogger;
use Statamic\Exceptions\RedirectException;
use Statamic\Testing\TestCase;

/**
 * @group redirects
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
     * @var RedirectsLogger
     */
    private $redirectsLogger;

    public function setUp()
    {
        parent::setUp();

        $this->storagePath = __DIR__ . '/temp/';
        $this->redirectsLogger = new RedirectsLogger($this->storagePath);
        $this->autoRedirectsManager = new AutoRedirectsManager($this->storagePath . 'auto.yaml', $this->redirectsLogger);

        $this->app->singleton(AutoRedirectsManager::class, $this->autoRedirectsManager);
    }

    public function test_redirect()
    {
        $redirect = (new AutoRedirect())
            ->setFromUrl('/not-existing-source')
            ->setToUrl('/target')
            ->setContentId('1234');

        $this->autoRedirectsManager
            ->add($redirect)
            ->flush();

        $this->get('/not-existing-source');

        $this->markTestSkipped();

        $this->assertRedirectedTo('/target');
    }

    public function tearDown()
    {
        parent::tearDown();

        unlink($this->storagePath . 'auto.yaml');
        unlink($this->storagePath . 'log_auto.yaml');
        unlink($this->storagePath . 'log_404.yaml');
    }
}
