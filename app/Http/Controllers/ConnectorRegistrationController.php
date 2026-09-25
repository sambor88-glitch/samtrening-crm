<?php

namespace App\Http\Controllers;

use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

/**
 * Client registration for Claude's connector — SC-68, docs/CLAUDE-CONNECTOR.md §2.
 *
 * The package only checks that a redirect starts with an allowed domain, so
 * `https://claude.ai/anything/../else` passes and a code could be steered to any page on that
 * site. Registration is open to anyone, which makes the redirect the one thing standing between a
 * stranger and the owner's "Połącz z Claude" tap: here it has to be one of Claude's callbacks,
 * character for character. Bound over the package controller in AppServiceProvider.
 */
class ConnectorRegistrationController extends OAuthRegisterController
{
    protected function isValidRedirectUri(string $value): bool
    {
        return in_array($value, config('mcp.redirect_uris'), true);
    }
}
