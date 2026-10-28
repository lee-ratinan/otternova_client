<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'login'
        ];
        return view('public_login', $data);
    }

    public function createAccount(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'create-account'
        ];
        return view('public_createAccount', $data);
    }
    public function forgotPassword(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'forgot-password'
        ];
        return view('public_forgotPassword', $data);
    }

    public function resetPassword(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'reset-password'
        ];
        return view('public_resetPassword', $data);
    }
}
