<?php

namespace App\Controllers;

class Admin extends BaseController
{
    /**
     * Landing page after sign-in (placeholder until the client dashboard is built).
     */
    public function dashboard(): string
    {
        $locale = $this->request->getLocale();
        $data   = [
            'locale' => $locale,
            'slug'   => 'dashboard',
            'user'   => session()->get('user') ?? [],
        ];
        return view('admin_dashboard', $data);
    }
}
