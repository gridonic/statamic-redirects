<?php

namespace Statamic\Addons\Redirects\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Statamic\Addons\Redirects\AutoRedirect;
use Statamic\Addons\Redirects\AutoRedirectsManager;
use Statamic\Addons\Redirects\RedirectsLogger;
use Statamic\Addons\Redirects\RedirectsManager;
use Statamic\API\Config;
use Statamic\Presenters\PaginationPresenter;

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

        return $this->paginatedItemsResponse($items, $this->getColumns(), $request);
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
     * @return \Illuminate\Support\Collection
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

        return $this->sortItems($items, $request);
    }
}
