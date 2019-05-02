<?php

namespace Statamic\Addons\Redirects\Controllers;

use Illuminate\Http\Request;
use Statamic\Addons\Redirects\AutoRedirect;
use Statamic\Addons\Redirects\AutoRedirectsManager;
use Statamic\Addons\Redirects\RedirectsLogger;
use Statamic\Addons\Redirects\RedirectsManager;

class AutoRedirectsController extends RedirectsController
{
    /**
     * @var RedirectsManager
     */
    private $autoRedirectsManager;

    /**
     * @var RedirectsLogger
     */
    private $redirectsLogger;

    public function __construct(AutoRedirectsManager $autoRedirectsManager, RedirectsLogger $redirectsLogger)
    {
        parent::__construct();

        $this->autoRedirectsManager = $autoRedirectsManager;
        $this->redirectsLogger = $redirectsLogger;
    }

    public function show()
    {
        return $this->view('auto_index', [
            'title' => $this->trans('common.auto_redirects'),
            'translations' => json_encode($this->trans('common')),
            'columns' => json_encode($this->getColumns()),
        ]);
    }

    public function get(Request $request)
    {
        $items = $this->buildRedirectItems($request);

        return [
            'items' => $items,
            'columns' => $this->getColumns(),
            'pagination' => [
                'totalItems' => count($items),
                'itemsPerPage' => count($items),
                'currentPage' => 1,
                'prevPage' => null,
                'nextPage' => null,
            ],
        ];
    }

    public function delete(Request $request)
    {
        $redirectIds = $request->input('ids');

        foreach ($redirectIds as $redirectId) {
            $route = base64_decode($redirectId);
            $this->autoRedirectsManager->remove($route);
        }

        $this->autoRedirectsManager->flush();

        return ['success' => true];
    }

    private function getColumns()
    {
        return [
            ['value' => 'from', 'header' => $this->trans('common.from')],
            ['value' => 'to', 'header' => $this->trans('common.to')],
            ['value' => 'hits', 'header' => $this->trans('common.hits')],
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    private function buildRedirectItems(Request $request)
    {
        $redirects = $this->autoRedirectsManager->all();
        $logs = $this->redirectsLogger->getAutoRedirects();

        $items = collect($redirects)->map(function ($redirect) use ($logs) {
            /** @var AutoRedirect $redirect */
            $id = base64_encode($redirect->getFromUrl());
            return array_merge($redirect->toArray(), [
                'id' => $id,
                'checked' => false,
                'hits' => isset($logs[$redirect->getFromUrl()]) ? $logs[$redirect->getFromUrl()] : 0,
            ]);
        });

        return $this->sortItems($items, $request)
            ->values()
            ->all();
    }
}
