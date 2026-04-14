<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Departement;
use App\Models\Groupe;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OTPHP\TOTP;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\TranslationService;
use App\Services\UserExportService;

class UserController extends Controller
{
    public function __construct(private readonly TranslationService $translations) {}

    /**
     * GET /api/users
     *
     * Liste paginée avec tous les filtres (délégués à buildFilteredQuery).
     */
    public function index(Request $request): JsonResponse
    {
        $users = $this->buildFilteredQuery(
            $request,
            ['roles:id,name', 'groupes:id,label_fr', 'departements:id,label_fr', 'attachments']
        )->paginate((int) ($request->per_page ?? 20));

        return response()->json($users);
    }

    /**
     * GET /api/users/export-excel
     * Export Excel du personnel avec filtres
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $users = $this->buildFilteredQuery($request, ['roles:id,name'])->get();

        AuditLog::log(
            'export_data',
            'data',
            null,
            null,
            ['format' => 'Excel', 'source' => 'Personnel', 'count' => $users->count(), 'filters' => $request->except(['page', 'per_page'])],
            'success',
            'Export Excel — Personnel (' . $users->count() . ' enregistrements)',
            null,
            'Personnel — Excel (' . $users->count() . ')'
        );

        $locale  = $request->get('language', 'fr');
        $labels  = $this->translations->many([
            'export.user.matricule', 'export.user.full_name_fr', 'export.user.full_name_ar',
            'export.user.login', 'export.user.roles', 'export.user.departement',
            'export.user.fonction', 'export.user.langue', 'export.user.email',
            'export.user.telephone', 'export.user.cin', 'export.user.date_entree',
            'export.user.active',
        ], $locale);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->translations->get('export.user.sheet_title', $locale, 'Personnel'));

        $headers = array_values($labels);
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = $sheet->getStyle('A1:M1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('2563EB');
        $headerStyle->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($users as $u) {
            $sheet->fromArray([
                $u->matricule,
                $u->full_name_fr,
                $u->full_name_ar,
                $u->login,
                $u->roles->pluck('name')->implode(', '),
                $u->departement,
                $u->fonction,
                $this->translations->get('export.user.langue_' . $u->langue, $locale, $u->langue),
                $u->email,
                $u->telephone,
                $u->cin,
                $u->date_entree ? substr($u->date_entree, 0, 10) : '',
                $u->active
                    ? $this->translations->get('export.common.yes', $locale, 'Oui')
                    : $this->translations->get('export.common.no', $locale, 'Non'),
            ], null, 'A' . $row);
            $row++;
        }

        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer): void {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="personnel.xlsx"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * GET /api/users/export-pdf
     * Export PDF du personnel avec filtres (mPDF)
     */
    public function exportPdf(Request $request, UserExportService $exportService): Response
    {
        $query = $this->buildFilteredQuery($request, ['roles:id,name']);

        $users = $query->get();

        $language  = $request->get('language', 'fr');
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename  = "personnel_{$timestamp}.pdf";

        $content = $exportService->generatePdf($users, $language, $filename);

        AuditLog::log(
            'export_data',
            'data',
            null,
            null,
            ['format' => 'PDF', 'source' => 'Personnel', 'count' => $users->count(), 'filters' => $request->except(['page', 'per_page', 'language'])],
            'success',
            'Export PDF — Personnel (' . $users->count() . ' enregistrements)',
            null,
            'Personnel — PDF (' . $users->count() . ')'
        );

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * GET /api/users/export-csv
     * Export CSV du personnel avec filtres
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $users = $this->buildFilteredQuery($request, ['roles:id,name'])->get();

        AuditLog::log(
            'export_data',
            'data',
            null,
            null,
            ['format' => 'CSV', 'source' => 'Personnel', 'count' => $users->count(), 'filters' => $request->except(['page', 'per_page'])],
            'success',
            'Export CSV — Personnel (' . $users->count() . ' enregistrements)',
            null,
            'Personnel — CSV (' . $users->count() . ')'
        );

        $locale    = $request->get('language', 'fr');
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename  = "personnel_{$timestamp}.csv";

        $labels  = $this->translations->many([
            'export.user.matricule', 'export.user.full_name_fr', 'export.user.full_name_ar',
            'export.user.login', 'export.user.roles', 'export.user.departement',
            'export.user.fonction', 'export.user.langue', 'export.user.email',
            'export.user.telephone', 'export.user.cin', 'export.user.date_entree',
            'export.user.active',
        ], $locale);
        $csvHeaders = array_values($labels);

        $yesLabel = $this->translations->get('export.common.yes', $locale, 'Oui');
        $noLabel  = $this->translations->get('export.common.no',  $locale, 'Non');

        return new StreamedResponse(function () use ($users, $csvHeaders, $locale, $yesLabel, $noLabel): void {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
            fputcsv($out, $csvHeaders, ';');
            foreach ($users as $u) {
                fputcsv($out, [
                    $u->matricule,
                    $u->full_name_fr,
                    $u->full_name_ar,
                    $u->login,
                    $u->roles->pluck('name')->implode(', '),
                    $u->departement,
                    $u->fonction,
                    $this->translations->get('export.user.langue_' . $u->langue, $locale, $u->langue),
                    $u->email,
                    $u->telephone,
                    $u->cin,
                    $u->date_entree ? substr($u->date_entree, 0, 10) : '',
                    $u->active ? $yesLabel : $noLabel,
                ], ';');
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * POST /api/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['roles', 'groupes', 'departements', 'photo', 'attachments']);

        // Photo upload
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('personnel/photos', 'public');
        }

        $user = User::create($data);

        // Sync roles
        if ($request->filled('roles')) {
            AuditLog::auditSync(
                $user,
                'roles',
                Role::whereIn('name', $request->roles)->pluck('id')->toArray(),
                'role_granted',
                'roles'
            );
        }

        // Sync groupes
        if ($request->filled('groupes')) {
            AuditLog::auditSync(
                $user,
                'groupes',
                Groupe::whereIn('label_fr', $request->groupes)->pluck('id')->toArray(),
                'record_updated',
                'data'
            );
        }

        // Sync departements
        if ($request->filled('departements')) {
            AuditLog::auditSync(
                $user,
                'departements',
                Departement::whereIn('label_fr', $request->departements)->pluck('id')->toArray(),
                'record_updated',
                'data'
            );
        }

        // Attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $user->attachments()->create([
                    'name'      => $file->getClientOriginalName(),
                    'type'      => $file->getClientMimeType(),
                    'file_path' => $file->store('personnel/attachments', 'public'),
                ]);
            }
        }

        $loaded = $user->load(['roles:id,name', 'attachments']);

        return response()->json($loaded, 201);
    }

    /**
     * GET /api/users/{user}
     */
    public function show(User $user): JsonResponse
    {
        return response()->json(
            $user->load(['roles:id,name', 'groupes:id,label_fr', 'departements:id,label_fr', 'attachments'])
        );
    }

    /**
     * PUT /api/users/{user}
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $data = $request->safe()->except(['roles', 'groupes', 'departements', 'photo', 'attachments']);

            // Password : only update if provided
            if (! $request->filled('password')) {
                unset($data['password']);
            }

            // Photo upload
            if ($request->hasFile('photo')) {
                // Delete old photo
                if ($user->photo) {
                    Storage::disk('public')->delete($user->photo);
                }
                $data['photo'] = $request->file('photo')->store('personnel/photos', 'public');
            }

            $user->update($data);

            // Sync roles
            if ($request->has('roles')) {
                AuditLog::auditSync(
                    $user,
                    'roles',
                    Role::whereIn('name', $request->roles)->pluck('id')->toArray(),
                    'role_granted',
                    'roles'
                );
            }

            // Sync groupes
            if ($request->has('groupes')) {
                AuditLog::auditSync(
                    $user,
                    'groupes',
                    Groupe::whereIn('label_fr', $request->groupes)->pluck('id')->toArray(),
                    'record_updated',
                    'data'
                );
            }

            // Sync departements
            if ($request->has('departements')) {
                AuditLog::auditSync(
                    $user,
                    'departements',
                    Departement::whereIn('label_fr', $request->departements)->pluck('id')->toArray(),
                    'record_updated',
                    'data'
                );
            }

            // New attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $user->attachments()->create([
                        'name'      => $file->getClientOriginalName(),
                        'type'      => $file->getClientMimeType(),
                        'file_path' => $file->store('personnel/attachments', 'public'),
                    ]);
                }
            }

            return response()->json(
                $user->load(['roles:id,name', 'attachments'])
            );
        } catch (\Exception $e) {
            Log::error('User update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(
                ['message' => 'Failed to update user: ' . $e->getMessage()],
                500
            );
        }
    }

    /**
     * DELETE /api/users/{user}
     *
     * Soft-delete. Impossible de se supprimer soi-même.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(
                ['message' => 'Impossible de supprimer votre propre compte.'],
                422
            );
        }

        // Clean photo
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    /**
     * PATCH /api/users/{user}/toggle-active
     */
    public function toggleActive(User $user): JsonResponse
    {
        $user->update(['active' => ! $user->active]);

        return response()->json($user);
    }

    /**
     * PATCH /api/users/{user}/toggle-tfa
     */
    public function toggleTfa(User $user): JsonResponse
    {
        if ($user->tfa_enabled) {
            $user->update(['tfa_enabled' => false, 'tfa_secret' => null]);
        } else {
            $totp = TOTP::create();
            $user->update(['tfa_enabled' => true, 'tfa_secret' => $totp->getSecret()]);
        }

        return response()->json($user->only(['id', 'tfa_enabled']));
    }

    /**
     * POST /api/users/{user}/photo
     *
     * Upload photo du personnel
     */
    public function uploadPhoto(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:5120', // max 5MB
        ]);

        // Delete old photo
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->update([
            'photo' => $request->file('photo')->store('personnel/photos', 'public'),
        ]);

        AuditLog::log(
            'record_updated',
            'data',
            $user,
            null,
            ['photo' => $user->photo],
            'success',
            'Photo mise à jour pour : ' . $user->full_name_fr
        );

        return response()->json(['photo' => $user->photo]);
    }

    /**
     * POST /api/users/{user}/attachments
     *
     * Upload pièces jointes du personnel
     */
    public function uploadAttachments(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'attachments' => 'required|array',
            'attachments.*' => 'file|max:5120', // max 5MB
        ]);

        $attachments = [];
        foreach ($request->file('attachments') ?? [] as $file) {
            $attachment = $user->attachments()->create([
                'name'      => $file->getClientOriginalName(),
                'type'      => $file->getClientMimeType(),
                'file_path' => $file->store('personnel/attachments', 'public'),
            ]);
            $attachments[] = $attachment;
        }

        $names = array_map(fn ($a) => $a->name, $attachments);

        AuditLog::log(
            'record_updated',
            'data',
            $user,
            null,
            ['attachments_added' => $names],
            'success',
            'Pièces jointes ajoutées (' . count($names) . ') pour : ' . $user->full_name_fr
        );

        return response()->json(['attachments' => $attachments]);
    }

    /**
     * DELETE /api/users/{user}/attachments/{attachment}
     */
    public function deleteAttachment(User $user, int $attachmentId): JsonResponse
    {
        $attachment = $user->attachments()->findOrFail($attachmentId);
        Storage::disk('public')->delete($attachment->file_path);

        AuditLog::log(
            'record_deleted',
            'data',
            $user,
            ['attachment' => $attachment->name, 'type' => $attachment->type],
            null,
            'success',
            'Pièce jointe supprimée : ' . $attachment->name . ' (personnel : ' . $user->full_name_fr . ')'
        );

        $attachment->delete();

        return response()->json(['message' => 'Pièce jointe supprimée.']);
    }

    /**
     * PUT /api/users/{user}/password
     *
     * Reset password (admin only)
     */
    public function updatePassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:4',
        ]);

        $user->update([
            'password' => bcrypt($request->password),
        ]);

        AuditLog::log(
            'password_change',
            'security',
            $user,
            null,
            null,
            'success',
            'Mot de passe modifié pour ' . $user->email
        );

        return response()->json(['message' => 'Mot de passe réinitialisé.']);
    }

    /**
     * POST /api/users/reorder
     *
     * Réordonner les utilisateurs par classement
     */
    public function reorder(Request $request): JsonResponse
    {
        $order = $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|string',
            'order.*.classement' => 'required|string',
        ])['order'];

        AuditLog::auditReorder(User::class, $order, 'classement');

        return response()->json(['message' => 'Ordre mis à jour.']);
    }

    // -------------------------------------------------------------------------
    // Helper partagé : requête filtrée pour les exports
    // -------------------------------------------------------------------------

    private function buildFilteredQuery(Request $request, array $with = [])
    {
        return User::with($with)
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where(function ($q) use ($request): void {
                    $q->where('full_name_fr', 'like', "%{$request->search}%")
                      ->orWhere('full_name_ar', 'like', "%{$request->search}%")
                      ->orWhere('email', 'like', "%{$request->search}%")
                      ->orWhere('matricule', 'like', "%{$request->search}%")
                      ->orWhere('cin', 'like', "%{$request->search}%")
                      ->orWhere('login', 'like', "%{$request->search}%");
                })
            )
            ->when($request->filled('active'),             fn ($q) => $q->where('active', (bool) $request->active))
            ->when($request->filled('fonction'),           fn ($q) => $q->where('fonction', $request->fonction))
            ->when($request->filled('departement'),        fn ($q) => $q->where('departement', $request->departement))
            ->when($request->filled('groupe'),             fn ($q) => $q->whereHas('groupes', fn ($gq) => $gq->where('label_fr', $request->groupe)))
            ->when($request->filled('langue'),             fn ($q) => $q->where('langue', $request->langue))
            ->when($request->filled('grade_avocat'),       fn ($q) => $q->where('grade_avocat', $request->grade_avocat))
            ->when($request->filled('avocat_proprietaire'), fn ($q) => $q->where('avocat_proprietaire', (bool) $request->avocat_proprietaire))
            ->when($request->filled('is_admin'),           fn ($q) => $q->where('is_admin', (bool) $request->is_admin))
            ->when($request->filled('cin'),                fn ($q) => $q->where('cin', 'like', "%{$request->cin}%"))
            ->when($request->filled('telephone'),          fn ($q) => $q->where('telephone', 'like', "%{$request->telephone}%"))
            ->when($request->filled('date_from'),          fn ($q) => $q->where('date_entree', '>=', $request->date_from))
            ->when($request->filled('date_to'),            fn ($q) => $q->where('date_entree', '<=', $request->date_to))
            ->when($request->filled('tfa_enabled'),        fn ($q) => $q->where('tfa_enabled', (bool) $request->tfa_enabled))
            ->when(
                $request->filled('sort_by'),
                function ($q) use ($request) {
                    $columnMap = [
                        'classement'  => 'classement',
                        'matricule'   => 'matricule',
                        'fullNameFr'  => 'full_name_fr',
                        'full_name_fr'=> 'full_name_fr',
                        'fullNameAr'  => 'full_name_ar',
                        'full_name_ar'=> 'full_name_ar',
                        'login'       => 'login',
                        'email'       => 'email',
                        'departement' => 'departement',
                        'fonction'    => 'fonction',
                        'langue'      => 'langue',
                    ];
                    $col = $columnMap[$request->sort_by] ?? null;
                    $dir = $request->get('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';
                    if ($col) {
                        $q->orderBy($col, $dir);
                    } else {
                        $q->orderBy('classement')->orderBy('full_name_fr');
                    }
                },
                fn ($q) => $q->orderBy('classement')->orderBy('full_name_fr')
            );
    }
}
