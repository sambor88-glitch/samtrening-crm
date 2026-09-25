<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Export\SessionCsvExport;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The accountant's CSV behind a link Claude hands out — SC-68, `Mcp\Tools\GetSessionReport`.
 *
 * The signature is the permission, as with a client's file (`ClientFileController`), but for
 * fifteen minutes instead of fourteen days. It names the account that asked, and that account has
 * to still be the active owner when the link is opened: blocking it kills every link it handed out.
 * The file is built on the spot and the export lands in the log under the owner's name, exactly as
 * when the button in the panel is pressed.
 */
class SessionReportController extends Controller
{
    public function __invoke(Request $request, SessionCsvExport $export): StreamedResponse
    {
        $actor = User::query()->find($request->query('by'));

        abort_unless($actor?->is_owner && $actor->status === UserStatus::Active, 403);

        $period = (string) $request->query('period');
        abort_unless((bool) preg_match('/^\d{4}(-(0[1-9]|1[0-2]))?$/', $period), 404);

        $file = $export->forStudio($actor, DateRange::fromPrefix($period));

        return response()->streamDownload(
            function () use ($file) {
                $output = fopen('php://output', 'w');
                fwrite($output, $file->contents);
                fclose($output);
            },
            $file->name,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
