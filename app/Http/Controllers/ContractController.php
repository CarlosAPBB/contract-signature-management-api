<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ContractMail;
use App\Mail\ContractSignedMail;
use App\Models\Contract;
use App\Models\Invitation;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Utils\StringUtils;
use Illuminate\Support\Facades\Mail;
use TCPDF;

class ContractController extends Controller
{

    public function getForSigning($contractId)
    {
        $contract = Contract::findOrFail($contractId);

        return response()->json([
            'contract' => $contract
        ]);
    }

    public function all()
    {
        $userId = auth()->id();

        $sentContracts = Contract::where('user_id', $userId)->get();

        $invitedContracts = Invitation::where('invited_user_id', $userId)
            ->with('contract')
            ->get()
            ->pluck('contract');

        return response()->json([
            'sentContracts' => $sentContracts,
            'invitedContracts' => $invitedContracts
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf|max:10000',
            'dynamic_fields' => 'nullable|string',
            'email' => 'required|email'
        ]);

        $loggedUserEmail = auth()->user()->email;

        if ($request->email === $loggedUserEmail) {
            return response()->json(['error' => 'cant_invite_yourself'], 400);
        }

        $invitedUser = User::where('email', $request->email)->first();

        if (!$invitedUser) {
            return response()->json(['error' => 'invited_user_not_found'], 404);
        }

        $filePath = $request->file('file')->store('contracts');

        $contract = Contract::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'file_path' => $filePath,
            'dynamic_fields' => $request->dynamic_fields,
            'status' => 'pending',
        ]);

        Invitation::create([
            'contract_id' => $contract->id,
            'invited_user_id' => $invitedUser->id
        ]);

        Mail::to($request->email)->send(new ContractMail($contract));

        return response()->json([
            'contract' => $contract
        ], 201);
    }

    public function sign(Request $request, $contractId)
    {
        $request->validate([
            'dynamic_fields' => 'required|string',
            'signature' => 'required|string'
        ]);

        $contract = Contract::findOrFail($contractId);
        $invitation = Invitation::where('contract_id', $contract->id)->first();

        $invitedUser = User::findOrFail($invitation->invited_user_id);
        $creator = User::findOrFail($contract->user_id);

        $user = auth()->user();

        $ipAddress = $request->ip();
        $userAgent = $request->header('User-Agent');
        $uniqueCode = StringUtils::generateUniqueCode($user->name, $ipAddress);

        $signaturePath = $this->saveSignature($request->input('signature'));

        $dynamicFields = json_decode($request->input('dynamic_fields'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'Error al procesar los campos dinámicos'], 400);
        }

        $pdfPath = $this->generateSignedPdf(
            $contract,
            $user,
            $signaturePath,
            $dynamicFields,
            $ipAddress,
            $userAgent,
            $uniqueCode
        );

        Signature::create([
            'contract_id' => $contract->id,
            'user_id' => $user->id,
            'signature_path' => $signaturePath,
            'final_pdf_path' => $pdfPath,
            'ip_address' => $ipAddress,
            'device_info' => $userAgent,
            'unique_code' => $uniqueCode
        ]);

        $contract->status = 'signed';
        $contract->save();

        Mail::to($creator->email)->send(new ContractSignedMail($contract, $pdfPath));
        Mail::to($invitedUser->email)->send(new ContractSignedMail($contract, $pdfPath));

        return response()->json(['message' => 'Contrato firmado exitosamente.', 'pdf_url' => $pdfPath]);
    }

    protected function saveSignature($dataUrl)
    {
        $imageParts = explode(";base64,", $dataUrl);
        $imageTypeAux = explode("image/", $imageParts[0]);
        $imageType = $imageTypeAux[1];
        $imageBase64 = base64_decode($imageParts[1]);

        $fileName = uniqid() . '.' . $imageType;
        Storage::disk('local')->put("signatures/{$fileName}", $imageBase64);

        return "signatures/{$fileName}";
    }

    protected function generateSignedPdf($contract, $user, $signaturePath, $dynamicFields, $ipAddress, $userAgent, $uniqueCode)
    {
        $originalPdf = Storage::disk('local')->path($contract->file_path);

        $pdf = new TCPDF();
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Contrato firmado', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, "Firmado por: {$user->name}", 0, 1);
        $pdf->Cell(0, 10, "Correo electrónico: {$user->email}", 0, 1);
        $pdf->Cell(0, 10, "Dirección IP: {$ipAddress}", 0, 1);
        $pdf->Cell(0, 10, "Navegador: {$userAgent}", 0, 1);
        $pdf->Cell(0, 10, "Código único: {$uniqueCode}", 0, 1);

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Campos rellenados:', 0, 1);

        $pdf->SetFont('helvetica', 'B', 10);
        foreach ($dynamicFields as $key => $value) {
            $pdf->Cell(0, 10, "{$key}: {$value}", 0, 1);
        }

        $signedPdfPath = 'contracts/signed_contract_' . uniqid() . '.pdf';
        Storage::disk('local')->put($signedPdfPath, $pdf->Output('', 'S'));
        $signedPdf = Storage::disk('local')->path($signedPdfPath);

        $finalPdfPath = $this->combinePdfs($originalPdf, $signedPdf);

        return $finalPdfPath;
    }

    protected function combinePdfs($originalPdf, $signedPdf)
    {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
        $pageCount = $pdf->setSourceFile($originalPdf);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplIdx = $pdf->importPage($pageNo);
            $pdf->AddPage();
            $pdf->useTemplate($tplIdx);
        }

        $pdf->setSourceFile($signedPdf);
        $tplIdx = $pdf->importPage(1);
        $pdf->AddPage();
        $pdf->useTemplate($tplIdx);

        $finalPdfPath = 'contracts/combined_contract_' . uniqid() . '.pdf';
        Storage::disk('local')->put($finalPdfPath, $pdf->Output('', 'S'));

        return $finalPdfPath;
    }

    public function download(Contract $contract)
    {
        $isOwner = auth()->id() === $contract->user_id;
        $isInvited = Invitation::where('contract_id', $contract->id)
            ->where('invited_user_id', auth()->id())
            ->exists();

        if (!$isOwner && !$isInvited) {
            return response()->json(['error' => 'unauthorized'], 403);
        }

        return response()->download(storage_path('app/' . $contract->file_path));
    }

    public function downloadSigned(Contract $contract)
    {
        $signature = $contract->signature()->first();

        if (!$signature) {
            return response()->json(['error' => 'Signature not found'], 404);
        }

        $isOwner = auth()->id() === $contract->user_id;
        $isInvited = Invitation::where('contract_id', $contract->id)
            ->where('invited_user_id', auth()->id())
            ->exists();

        if (!$isOwner && !$isInvited) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $pdfPath = storage_path('app/' . $signature->final_pdf_path);

        if (!file_exists($pdfPath)) {
            return response()->json(['error' => 'Signed PDF not found'], 404);
        }

        return response()->download($pdfPath);
    }

    public function downloadSignature(Contract $contract)
    {
        $signature = $contract->signature()->first();

        if (!$signature) {
            return response()->json(['error' => 'Signature not found'], 404);
        }

        $isOwner = auth()->id() === $contract->user_id;
        $isInvited = Invitation::where('contract_id', $contract->id)
            ->where('invited_user_id', auth()->id())
            ->exists();

        if (!$isOwner && !$isInvited) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $signaturePath = storage_path('app/' . $signature->signature_path);

        if (!file_exists($signaturePath)) {
            return response()->json(['message' => 'Firma no encontrada'], 404);
        }

        return response()->download($signaturePath);
    }

    public function reject(Contract $contract)
    {
        $isInvited = Invitation::where('contract_id', $contract->id)
            ->where('invited_user_id', auth()->id())
            ->exists();

        if (!$isInvited) {
            return response()->json(['error' => 'unauthorized_to_reject_contract'], 403);
        }

        $contract->status = 'rejected';
        $contract->save();

        return response()->json(['message' => 'Contrato rechazado exitosamente.']);
    }
}
