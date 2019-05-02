<?php

namespace Statamic\Addons\Redirects\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Statamic\Extend\Controller;

abstract class RedirectsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->checkAccess();
    }

    protected function sortItems(Collection $items, Request $request)
    {
        if (!$request->get('sort')) {
            return $items;
        }

        $method = $request->get('order', 'asc') === 'asc' ? 'sortBy' : 'sortByDesc';

        return $items->$method($request->get('sort'));
    }

    private function checkAccess()
    {
        //        throw new UnauthorizedHttpException(403);

    }
}
