<?php

namespace Syntax\LaravelMailIntegration\Contracts;

use Illuminate\Http\Request;

interface MailClient
{
    public function send(Request $request);
}
