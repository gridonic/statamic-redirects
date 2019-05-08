<?php

namespace Statamic\Addons\Redirects\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Statamic\Addons\Redirects\RedirectsAuthTrait;
use Statamic\API\User;
use Statamic\Exceptions\UnauthorizedHttpException;
use Statamic\Extend\Controller;

abstract class RedirectsController extends Controller
{
    use RedirectsAuthTrait;

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
        if (!$this->hasAccess(User::getCurrent())) {
            throw new UnauthorizedHttpException(403);
        }
    }
}
