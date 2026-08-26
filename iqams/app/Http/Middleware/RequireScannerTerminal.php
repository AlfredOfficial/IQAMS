<?php

namespace App\Http\Middleware;

use App\Models\ScannerTerminal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireScannerTerminal
{
    public function handle(Request $request, Closure $next): Response
    {
        $terminal = ScannerTerminal::whereKey($request->session()->get('scanner_terminal_id'))->where('is_active', true)->first();
        abort_unless($terminal, 403, 'Select an active registered scanner terminal before scanning.');
        $request->attributes->set('scanner_terminal', $terminal);

        return $next($request);
    }
}
