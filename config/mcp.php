<?php

/*
 * Claude's connector — docs/CLAUDE-CONNECTOR.md, SC-68.
 *
 * Anybody on the internet may register an OAuth client (that is how claude.ai finds its way in),
 * so the redirect is what keeps a stranger's app from collecting a code: it can only ever be one
 * of Claude's own callbacks, compared whole (`ConnectorRegistrationController`). The consent screen
 * and the owner-only check behind /mcp do the rest.
 */
return [

    // Exact callbacks, not prefixes. claude.com is where Anthropic says the callback may move.
    'redirect_uris' => [
        'https://claude.ai/api/mcp/auth_callback',
        'https://claude.com/api/mcp/auth_callback',
    ],

    // The package's own, looser check still runs after ours; it has to agree.
    'redirect_domains' => [
        'https://claude.ai',
        'https://claude.com',
    ],

    // Desktop apps with their own URL schemes (cursor://, vscode://) are not invited.
    'custom_schemes' => [],

    // null = url('/'), the address the app is served on.
    'authorization_server' => null,

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

];
