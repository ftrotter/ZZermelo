<?php

namespace ftrotter\ZZermelo\Http\Controllers;

use ftrotter\ZZermelo\Http\Requests\CardsReportRequest;
use ftrotter\ZZermeloBladeTreeCard\TreeCardPresenter;
use Illuminate\Support\Facades\Auth;

class TreeCardController
{
    public function show( CardsReportRequest $request )
    {
        $presenter = new TreeCardPresenter( $request->buildReport() );

        $presenter->setApiPrefix( api_prefix() );
        $presenter->setReportPath( tree_api_prefix() );

        $user = Auth::guard()->user();
        if ( $user ) {
            $presenter->setToken( $user->getRememberToken() );
        }

        $view = config("zzermelo.TREE_CARD_VIEW_TEMPLATE");

        return view( $view, [ 'presenter' => $presenter ] );
    }
}
