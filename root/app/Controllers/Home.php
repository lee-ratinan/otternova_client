<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
        ];
        return view('public_login', $data);
    }
}
