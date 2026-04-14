<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuditController extends Controller
{
    /**
     * GET /api/audit-logs
     *
     * Liste paginée des logs d'audit avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . $request->search . '%';
                $q->where(function ($q) use ($s) {
                    $q->where('user_name', 'like', $s)
                      ->orWhere('auditable_label', 'like', $s)
                      ->orWhere('description', 'like', $s)
                      ->orWhere('auditable_type', 'like', $s);
                });
            })
            ->when($request->filled('category'), function ($q) use ($request) {
                $q->where('category', $request->category);
            })
            ->when($request->filled('action'), function ($q) use ($request) {
                $q->where('action', $request->action);
            })
            ->when($request->filled('result'), function ($q) use ($request) {
                $q->where('result', $request->result);
            })
            ->when($request->filled('user_id'), function ($q) use ($request) {
                if ($request->user_id == '0') {
                    $q->whereNull('user_id');
                } else {
                    $q->where('user_id', $request->user_id);
                }
            })
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->date_to);
            });

        // Sorting
        $allowedSorts = ['created_at', 'user_name', 'action', 'category', 'result', 'ip_address', 'auditable_label'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts) ? $request->input('sort_by') : 'created_at';
        $sortDir = $request->input('sort_dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = min((int) $request->input('per_page', 30), 100);
        $paginated = $query->paginate($perPage);

        return response()->json($paginated);
    }

    /**
     * GET /api/audit-logs/users
     *
     * Liste distincte des utilisateurs ayant des entrées d'audit.
     */
    public function users(): JsonResponse
    {
        $users = AuditLog::query()
            ->selectRaw('COALESCE(user_id, 0) as user_id, user_name')
            ->distinct()
            ->orderBy('user_name')
            ->get();

        return response()->json($users);
    }

    /**
     * GET /api/audit-logs/stats
     *
     * Statistiques pour les cartes du dashboard audit.
     */
    public function stats(): JsonResponse
    {
        $now = now();
        $h24 = $now->copy()->subHours(24);
        $d7 = $now->copy()->subDays(7);

        return response()->json([
            'total'              => AuditLog::count(),
            'today'              => AuditLog::whereDate('created_at', today())->count(),
            'failed_logins_24h'  => AuditLog::where('action', 'login_failure')->where('created_at', '>=', $h24)->count(),
            'password_resets_7d' => AuditLog::where('action', 'password_change')->where('created_at', '>=', $d7)->count(),
            'role_changes_7d'    => AuditLog::whereIn('action', ['role_granted', 'role_revoked'])->where('created_at', '>=', $d7)->count(),
            'records_created_7d' => AuditLog::where('action', 'record_created')->where('created_at', '>=', $d7)->count(),
            'records_updated_7d' => AuditLog::where('action', 'record_updated')->where('created_at', '>=', $d7)->count(),
            'records_deleted_7d' => AuditLog::where('action', 'record_deleted')->where('created_at', '>=', $d7)->count(),
        ]);
    }

    /**
     * GET /api/audit-logs/{auditLog}
     *
     * Détail d'un log d'audit avec les valeurs avant/après.
     */
    public function show(AuditLog $auditLog): JsonResponse
    {
        return response()->json([
            'data' => $auditLog,
        ]);
    }

    /**
     * GET /api/audit-logs/export/xlsx
     *
     * Export XLSX des logs filtrés.
     */
    public function exportXlsx(Request $request, AuditExportService $exportService): Response
    {
        $query = $this->buildExportQuery($request);
        $language = $request->query('lang', 'fr');
        $count = (clone $query)->count();

        AuditLog::log(
            'export_data',
            'security',
            null,
            null,
            ['format' => 'XLSX', 'source' => 'Journal d\'audit', 'count' => $count],
            'success',
            'Export XLSX — Journal d\'audit (' . $count . ' enregistrements)',
            null,
            'Journal d\'audit — XLSX (' . $count . ')'
        );

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "audit-logs_{$timestamp}.xlsx";
        $content = $exportService->generateXlsx($query, $language);

        return response($content)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * GET /api/audit-logs/export/pdf
     *
     * Export PDF des logs filtrés.
     */
    public function exportPdf(Request $request, AuditExportService $exportService): Response
    {
        $query = $this->buildExportQuery($request);
        $language = $request->query('lang', 'fr');
        $count = (clone $query)->count();

        AuditLog::log(
            'export_data',
            'security',
            null,
            null,
            ['format' => 'PDF', 'source' => 'Journal d\'audit', 'count' => $count],
            'success',
            'Export PDF — Journal d\'audit (' . $count . ' enregistrements)',
            null,
            'Journal d\'audit — PDF (' . $count . ')'
        );

        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "audit-logs_{$timestamp}.pdf";
        $content = $exportService->generatePdf($query, $language, $filename);

        return response($content)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * GET /api/audit-logs/export/csv
     *
     * Export CSV des logs filtrés (pour intégration dans d'autres applications).
     */
    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = $this->buildExportQuery($request);
        $count = (clone $query)->count();

        AuditLog::log(
            'export_data',
            'security',
            null,
            null,
            ['format' => 'CSV', 'source' => 'Journal d\'audit', 'count' => $count],
            'success',
            'Export CSV — Journal d\'audit (' . $count . ' enregistrements)',
            null,
            'Journal d\'audit — CSV (' . $count . ')'
        );

        $filename = 'audit-logs-' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, ['Date', 'Utilisateur', 'Action', 'Catégorie', 'Résultat', 'Modèle', 'Enregistrement', 'IP', 'Description']);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user_name,
                        $log->action,
                        $log->category,
                        $log->result,
                        $log->auditable_type ? class_basename($log->auditable_type) : '',
                        $log->auditable_label ?? '',
                        $log->ip_address ?? '',
                        $log->description ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    /**
     * Construire la requête de base pour l'export (filtres communs).
     */
    private function buildExportQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return AuditLog::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('result'), fn ($q) => $q->where('result', $request->result))
            ->when($request->filled('user_id'), function ($q) use ($request) {
                if ($request->user_id == '0') {
                    $q->whereNull('user_id');
                } else {
                    $q->where('user_id', $request->user_id);
                }
            })
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderByDesc('created_at');
    }
}
