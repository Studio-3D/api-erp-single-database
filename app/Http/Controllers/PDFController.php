<?php
// app/Http/Controllers/PDFController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PDFController extends Controller
{
    public function generateCompromisPDF(Request $request)
    {
        try {
            $data = $request->input('data');

            if (!$data) {
                return response()->json(['error' => 'No data provided'], 400);
            }

            // Process logo if exists (supports both local and S3)
            $logoBase64 = null;
            if (isset($data['user']['societe']['logo']) &&
                isset($data['user']['societe']['raison_sociale_concatene']) &&
                isset($data['user']['societe']['id'])) {

                $societe = $data['user']['societe'];
                $logoFilename = $societe['logo'];
                $logoPath = $societe['raison_sociale_concatene'] . '_' . $societe['id'] . '/logos/' . $logoFilename;

                $fileContent = null;

                // Check if in production (S3) or local
                if (app()->environment('production')) {
                    // Get from S3
                    if (Storage::disk('s3')->exists($logoPath)) {
                        $fileContent = Storage::disk('s3')->get($logoPath);
                    }
                } else {
                    // Get from local public/docs
                    $localPath = public_path('docs/' . $logoPath);
                    if (file_exists($localPath)) {
                        $fileContent = file_get_contents($localPath);
                    }
                }

                if ($fileContent !== null) {
                    // Detect MIME type
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);

                    if (app()->environment('production')) {
                        // For S3, we can get from storage or detect from extension
                        $extension = pathinfo($logoFilename, PATHINFO_EXTENSION);
                        $mimeType = match($extension) {
                            'png' => 'image/png',
                            'jpg', 'jpeg' => 'image/jpeg',
                            'gif' => 'image/gif',
                            'svg' => 'image/svg+xml',
                            default => 'image/png'
                        };
                    } else {
                        $localPath = public_path('docs/' . $logoPath);
                        $mimeType = finfo_file($finfo, $localPath);
                        finfo_close($finfo);
                    }

                    $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
                }
            }

            // Prepare all data for the view
            $pdfData = [
                'user' => $data['user'],
                'societe' => $data['user']['societe'] ?? null,
                'logoBase64' => $logoBase64,
                'num_recu' => $data['num_recu'],
                'clients' => $data['clients'],
                'reservationDetails' => $data['reservationDetails'],
                'sum_avances_valides' => $data['sum_avances_valides'],
                'form' => $data['form'],
                'currentDate' => now()->format('d/m/Y'),
                'formatCivilite' => function($civilite) {
                    switch ($civilite) {
                        case "1": return "Monsieur";
                        case "2": return "Madame";
                        case "3": return "Mademoiselle";
                        default: return $civilite;
                    }
                }
            ];

            // Generate PDF
            $pdf = Pdf::loadView('pdfs.compromis_vente', $pdfData);
            $pdf->setPaper('A4', 'portrait');

            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isPhpEnabled' => true,
                'defaultFont' => 'dejavu sans',
                'chroot' => public_path(),
            ]);

            return $pdf->download("compromis_vente_{$data['num_recu']}.pdf");

        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
        }
    }

   /****************************avanceeess*******************************/


        public function generateRecuVentePDF(Request $request)
        {
            try {
                $data = $request->input('data');

                if (!$data) {
                    return response()->json(['error' => 'No data provided'], 400);
                }

                // Process logo if exists
                $logoBase64 = null;
                if (isset($data['user']['societe']['logo']) &&
                    isset($data['user']['societe']['raison_sociale_concatene']) &&
                    isset($data['user']['societe']['id'])) {

                    $societe = $data['user']['societe'];
                    $logoFilename = $societe['logo'];
                    $logoPath = $societe['raison_sociale_concatene'] . '_' . $societe['id'] . '/logos/' . $logoFilename;

                    $fileContent = null;

                    // Check if in production (S3) or local
                    if (app()->environment('production')) {
                        if (Storage::disk('s3')->exists($logoPath)) {
                            $fileContent = Storage::disk('s3')->get($logoPath);
                        }
                    } else {
                        $localPath = public_path('docs/' . $logoPath);
                        if (file_exists($localPath)) {
                            $fileContent = file_get_contents($localPath);
                        }
                    }

                    if ($fileContent !== null) {
                        $extension = pathinfo($logoFilename, PATHINFO_EXTENSION);
                        $mimeType = match($extension) {
                            'png' => 'image/png',
                            'jpg', 'jpeg' => 'image/jpeg',
                            'gif' => 'image/gif',
                            'svg' => 'image/svg+xml',
                            default => 'image/png'
                        };
                        $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
                    }
                }

                // Get client names string
                $clientNames = $this->getClientNames($data['clients'] ?? []);

                // Get client CINs
                $clientCINs = implode(', ', array_filter(array_column($data['clients'] ?? [], 'cin')));

                // Get client addresses
                $clientAddresses = implode(', ', array_filter(array_column($data['clients'] ?? [], 'adresse')));

                // Convert montant to words
                $montantEnLettres = $this->numberToWords($data['montant'] ?? 0);
                 $societeData = $data['user']['societe'] ?? [];
                // Prepare all data for the view
                $pdfData = [
                    'logoBase64' => $logoBase64,
                    'num_recu' => $data['num_recu'] ?? ' ',
                    'clients' => $data['clients'] ?? [],
                    'clientNames' => $clientNames,
                    'clientCINs' => $clientCINs,
                    'clientAddresses' => $clientAddresses,
                    'bien' => $data['bien'] ?? [],
                    'mode_paiement' => $data['mode_paiement'] ?? null,
                    'banque' => $data['banque'] ?? '',
                    'numero_paiement' => $data['numero_paiement'] ?? '',
                    'montant' => $data['montant'] ?? 0,
                    'montantEnLettres' => $montantEnLettres,
                    'date_recu' => $data['date_recu'] ?? now()->format('d/m/Y'),
                    'role_nom' => $data['role_nom'] ?? '',
                    'role' => $data['role'] ?? '',

                    'raison_social' => $societeData['raison_social'] ?? '',
                    'capital' => $societeData['capital']  ?? 0,
                    'adresse' => $societeData['adresse'] ?? '',
                    'tel' =>$societeData['tel'] ?? '',
                    'email' =>$societeData['email'] ?? '',
                    'registre_commerce' => $societeData['registre_commerce']  ?? '',
                    'id_fiscal' => $societeData['id_fiscal'] ??'',

                    'code_reservation' => $data['reservationDetails']['code_reservation'] ?? '',
                    'currentDate' => now()->format('d/m/Y'),
                ];

                // Generate PDF
                $pdf = Pdf::loadView('pdfs.recu_vente', $pdfData);
                $pdf->setPaper('A4', 'portrait');

                $pdf->setOptions([
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => false,
                    'isPhpEnabled' => true,
                    'defaultFont' => 'dejavu sans',
                ]);

                return $pdf->download("recu_{$data['num_recu']}.pdf");

            } catch (\Exception $e) {
                Log::error('PDF Generation Error: ' . $e->getMessage());
                Log::error('Stack trace: ' . $e->getTraceAsString());
                return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
            }
        }

        // Helper function to get client names
        private function getClientNames($clients)
        {
            $names = [];
            foreach ($clients as $client) {
                $civilite = isset($client['civilite']) ? ($client['civilite'] == '1' ? 'Mr' : ($client['civilite'] == '2' ? 'Mme' : 'Mlle')) : '';
                $names[] = trim($civilite . ' ' . ($client['nom'] ?? '') . ' ' . ($client['prenom'] ?? ''));
            }
            return implode(', ', array_filter($names));
        }

        // Helper function to format currency
        private function formatCurrency($amount)
        {
            return number_format($amount, 2, ',', ' ') . ' DH';
        }

        // Helper function to convert number to words
        private function numberToWords($num)
        {
            if ($num == 0) return 'zéro';

            $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
            $tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix'];

            $convertLessThanThousand = function($n) use ($units, $tens, &$convertLessThanThousand) {
                if ($n === 0) return '';
                if ($n < 20) return $units[$n];
                if ($n < 100) {
                    $unit = $n % 10;
                    $ten = floor($n / 10);
                    if ($ten === 7 || $ten === 9) {
                        if ($unit === 1) return $tens[$ten - 1] . ' et onze';
                        return $tens[$ten - 1] . '-' . $units[$unit + 10];
                    }
                    if ($unit === 1 && $ten !== 8) return $tens[$ten] . ' et un';
                    if ($unit === 0) return $tens[$ten];
                    if ($ten === 8) return $tens[$ten] . '-' . $units[$unit];
                    return $tens[$ten] . '-' . $units[$unit];
                }
                $hundred = floor($n / 100);
                $rest = $n % 100;
                if ($rest === 0) {
                    return $units[$hundred] . ' cent' . ($hundred > 1 ? 's' : '');
                }
                return $units[$hundred] . ' cent ' . $convertLessThanThousand($rest);
            };

            $result = '';
            if ($num >= 1000000) {
                $millions = floor($num / 1000000);
                $result .= $convertLessThanThousand($millions) . ' million' . ($millions > 1 ? 's' : '') . ' ';
                $num %= 1000000;
            }
            if ($num >= 1000) {
                $thousands = floor($num / 1000);
                if ($thousands === 1) {
                    $result .= 'mille ';
                } else {
                    $result .= $convertLessThanThousand($thousands) . ' mille ';
                }
                $num %= 1000;
            }
            if ($num > 0) {
                $result .= $convertLessThanThousand($num);
            }

            return trim($result) . ' dirhams marocains';
        }

        private function getPaymentModeLabel($mode)
        {
            switch ((int)$mode) {
                case 1: return 'Espèces';
                case 2: return 'Chèque';
                case 3: return 'Chèque de banque';
                case 4: return 'Chèque certifié';
                case 5: return 'Virement bancaire';
                case 6: return 'Versement';
                default: return '';
            }
        }
         /****************************fiche prospect*******************************/
        public function generateProspectPDF(Request $request)
    {
        try {
            $data = $request->input('data');

            if (!$data) {
                return response()->json(['error' => 'No data provided'], 400);
            }

            $prospect = $data['prospect'];
            $appels = $data['appels'] ?? [];
            $visites = $data['visites'] ?? [];
            $user = $data['user'] ?? [];
            $societe = $user['societe'] ?? [];

            // Process logo if exists
            $logoBase64 = null;
            if (isset($societe['logo']) &&
                isset($societe['raison_sociale_concatene']) &&
                isset($societe['id'])) {

                $logoFilename = $societe['logo'];
                $logoPath = $societe['raison_sociale_concatene'] . '_' . $societe['id'] . '/logos/' . $logoFilename;

                $fileContent = null;

                if (app()->environment('production')) {
                    if (Storage::disk('s3')->exists($logoPath)) {
                        $fileContent = Storage::disk('s3')->get($logoPath);
                    }
                } else {
                    $localPath = public_path('docs/' . $logoPath);
                    if (file_exists($localPath)) {
                        $fileContent = file_get_contents($localPath);
                    }
                }

                if ($fileContent !== null) {
                    $extension = pathinfo($logoFilename, PATHINFO_EXTENSION);
                    $mimeType = match($extension) {
                        'png' => 'image/png',
                        'jpg', 'jpeg' => 'image/jpeg',
                        'gif' => 'image/gif',
                        'svg' => 'image/svg+xml',
                        default => 'image/png'
                    };
                    $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
                }
            }

            // Prepare data for view
            $pdfData = [
                'logoBase64' => $logoBase64,
                'societe' => $societe,
                'prospect' => $prospect,
                'appels' => $appels,
                'visites' => $visites,
                'currentDate' => now()->format('d/m/Y H:i'),
                'formatInteret' => function($interet) {
                    switch ($interet) {
                        case "1": return "Intéressé";
                        case "2": return "Réceptif";
                        case "3": return "Perdu";
                        case "4": return "Injoignable";
                        case "5": return "Suivi Dossier";
                        default: return "";
                    }
                },
                'formatStatutVisite' => function($statut) {
                    switch ($statut) {
                        case "1": return "Pré-Réservation";
                        case "2": return "Vendu";
                        case "3": return "Pré-Réservation Perdu";
                        case "4": return "Réservation Perdu";
                        case "5": return "Pré-Réservation Vendu";
                        default: return "";
                    }
                },
                'formatDate' => function($date) {
                    if (!$date) return '';
                    return \Carbon\Carbon::parse($date)->format('d/m/Y H:i');
                },
                'getBienInfo' => function($bien) {
                    if (!$bien) return '';
                    $noms = [];
                    if (isset($bien['tranche']['nom'])) $noms[] = $bien['tranche']['nom'];
                    if (isset($bien['bloc']['nom'])) $noms[] = $bien['bloc']['nom'];
                    if (isset($bien['immeuble']['nom'])) $noms[] = $bien['immeuble']['nom'];
                    if (isset($bien['propriete_dite_bien'])) $noms[] = $bien['propriete_dite_bien'];
                    return implode(' - ', $noms);
                }
            ];

            // Generate PDF
            $pdf = Pdf::loadView('pdfs.prospect_fiche', $pdfData);
            $pdf->setPaper('A4', 'portrait');

            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isPhpEnabled' => true,
                'defaultFont' => 'dejavu sans',
            ]);

            $filename = 'fiche_prospect_' . ($prospect['nom'] ?? '') . '_' . ($prospect['prenom'] ?? '') . '.pdf';
            $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);

            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error('Prospect PDF Generation Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
        }
    }

    /********************************CLIENT PDF************************/

    public function generateClientPDF(Request $request)
{
    try {
        $data = $request->input('data');

        if (!$data) {
            return response()->json(['error' => 'No data provided'], 400);
        }

        // Process logo
        $logoBase64 = null;
        if (isset($data['user']['societe']['logo']) &&
            isset($data['user']['societe']['raison_sociale_concatene']) &&
            isset($data['user']['societe']['id'])) {

            $societe = $data['user']['societe'];
            $logoFilename = $societe['logo'];
            $logoPath = $societe['raison_sociale_concatene'] . '_' . $societe['id'] . '/logos/' . $logoFilename;

            $fileContent = null;

            if (app()->environment('production')) {
                if (Storage::disk('s3')->exists($logoPath)) {
                    $fileContent = Storage::disk('s3')->get($logoPath);
                }
            } else {
                $localPath = public_path('docs/' . $logoPath);
                if (file_exists($localPath)) {
                    $fileContent = file_get_contents($localPath);
                }
            }

            if ($fileContent !== null) {
                $extension = pathinfo($logoFilename, PATHINFO_EXTENSION);
                $mimeType = match($extension) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    default => 'image/png'
                };
                $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
            }
        }

        $client = $data['client'] ?? [];
        $reservations = $data['reservations'] ?? [];
        $visites = $data['visites'] ?? [];
        $user = $data['user'] ?? [];
        $societe = $user['societe'] ?? [];

        // Helper functions
        $formatCivilite = function($civilite) {
            switch ($civilite) {
                case "1": return "Monsieur";
                case "2": return "Madame";
                case "3": return "Mademoiselle";
                default: return "";
            }
        };

        $getSituationLabel = function($situation) {
            switch ($situation) {
                case "1": return "Célibataire";
                case "2": return "Marié(e)";
                case "3": return "Divorcé(e)";
                case "4": return "Veuf/Veuve";
                default: return "";
            }
        };

        $formatFinancement = function($mode) {
            switch ($mode) {
                case "1": return "Comptant";
                case "2": return "Crédit";
                case "3": return "Indécis";
                default: return "";
            }
        };

        $formatStatutReservation = function($statut) {
            switch ($statut) {
                case "1": return "Validé";
                case "2": return "Refusé";
                case "3": return "En Attente";
                default: return "";
            }
        };

        $formatInteret = function($interet) {
            switch ($interet) {
                case "1": return "Intéressé";
                case "2": return "Réceptif";
                case "3": return "Perdu";
                case "4": return "Injoignable";
                case "5": return "Suivi Dossier";
                default: return "";
            }
        };

        $formatStatutVisite = function($statut) {
            switch ($statut) {
                case "1": return "Pré-Réservation";
                case "2": return "Vendu";
                case "3": return "Pré-Réservation Perdu";
                case "4": return "Réservation Perdu";
                case "5": return "Pré-Réservation Vendu";
                default: return "";
            }
        };

        $getBienInfo = function($bien) {
            if (!$bien) return '';
            $noms = [];
            if (isset($bien['tranche']['nom'])) $noms[] = $bien['tranche']['nom'];
            if (isset($bien['bloc']['nom'])) $noms[] = $bien['bloc']['nom'];
            if (isset($bien['immeuble']['nom'])) $noms[] = $bien['immeuble']['nom'];
            if (isset($bien['propriete_dite_bien'])) $noms[] = $bien['propriete_dite_bien'];
            return implode(' - ', $noms);
        };

        $pdfData = [
            'logoBase64' => $logoBase64,
            'client' => $client,
            'reservations' => $reservations,
            'visites' => $visites,
            'societe' => $societe,
            'currentDate' => now()->format('d/m/Y H:i'),
            'formatCurrency' => function($amount) {
                return number_format($amount, 0, ',', ' ') . ' DH';
            },
            'formatDate' => function($date) {
                if (!$date) return '';
                return \Carbon\Carbon::parse($date)->format('d/m/Y');
            },
            'formatCivilite' => $formatCivilite,
            'getSituationLabel' => $getSituationLabel,
            'formatFinancement' => $formatFinancement,
            'formatStatutReservation' => $formatStatutReservation,
            'formatInteret' => $formatInteret,
            'formatStatutVisite' => $formatStatutVisite,
            'getBienInfo' => $getBienInfo,
        ];

        $pdf = Pdf::loadView('pdfs.client_fiche', $pdfData);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'isPhpEnabled' => true,
            'defaultFont' => 'dejavu sans',
        ]);

        $filename = 'client_' . ($client['code_client'] ?? $client['id'] ?? 'fiche') . '.pdf';
        return $pdf->download($filename);

    } catch (\Exception $e) {
        Log::error('Client PDF Generation Error: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
    }
}
/*********************************pdf bon pre reservation *****************/


public function generateBonPreReservationPDF(Request $request)
{
    try {
        $data = $request->input('data');

        if (!$data) {
            return response()->json(['error' => 'No data provided'], 400);
        }

        // Process logo
        $logoBase64 = null;
        $user = $data['user'] ?? [];
        $societe = $user['societe'] ?? [];

        if (isset($societe['logo']) &&
            isset($societe['raison_sociale_concatene']) &&
            isset($societe['id'])) {

            $logoFilename = $societe['logo'];
            $logoPath = $societe['raison_sociale_concatene'] . '_' . $societe['id'] . '/logos/' . $logoFilename;

            $fileContent = null;

            if (app()->environment('production')) {
                if (Storage::disk('s3')->exists($logoPath)) {
                    $fileContent = Storage::disk('s3')->get($logoPath);
                }
            } else {
                $localPath = public_path('docs/' . $logoPath);
                if (file_exists($localPath)) {
                    $fileContent = file_get_contents($localPath);
                }
            }

            if ($fileContent !== null) {
                $extension = pathinfo($logoFilename, PATHINFO_EXTENSION);
                $mimeType = match($extension) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    default => 'image/png'
                };
                $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
            }
        }

       $pdfData = [
    'logoBase64' => $logoBase64,
    'societe' => $societe,
    'code_pre_reservation' => $data['code_pre_reservation'] ?? '',
    'date_pre_reserve' => $data['date_pre_reserve'] ?? '',
    'rdv' => $data['rdv'] ?? '',
    'propriete_dite_bien' => $data['propriete_dite_bien'] ?? '',
    'niveau' => $data['niveau'] ?? '',
    'superficie' => $data['superficie'] ?? '',
    'orientation' => $data['orientation'] ?? '',
    'prix' => $data['prix'] ?? 0,
    'commercial_nom' => $data['commercial_nom'] ?? '',
    'commercial_prenom' => $data['commercial_prenom'] ?? '',
    'currentDate' => now()->format('d/m/Y'),
    'formatCurrency' => function($amount) {
        return number_format($amount, 0, ',', ' ') . ' DH';
    },
    // Ajouter ces deux fonctions
    'getOrientationFullName' => function($abbreviation) {
        $orientationMap = [
            'N' => 'Nord',
            'S' => 'Sud',
            'E' => 'Est',
            'O' => 'Ouest',
            'N_E' => 'Nord-Est',
            'N_O' => 'Nord-Ouest',
            'S_E' => 'Sud-Est',
            'S_O' => 'Sud-Ouest',
            'NORD_SUD' => 'Nord/Sud',
            'NORD_OUEST' => 'Nord-Ouest',
            'SUD_EST' => 'Sud-Est',
            'EST_OUEST' => 'Est/Ouest',
            'NO_SE' => 'Nord-Ouest / Sud-Est',
            'NORD_SUD_OUEST' => 'Nord/Sud/Ouest',
            'NORD_SUD_EST' => 'Nord-Ouest / Sud-Est',
            'NORD_EST_OUEST' => 'Nord/Est/Ouest',
        ];
        return $orientationMap[$abbreviation] ?? ($abbreviation ?: '');
    },
    'getNiveauText' => function($niveau) {
        if ($niveau === null || $niveau === '') {
            return 'au rez-de-chaussée';
        }
        $niveau = (int)$niveau;
        if ($niveau == 0) {
            return 'au rez-de-chaussée (RDC)';
        } elseif ($niveau == 1) {
            return 'au 1er étage';
        } else {
            return 'au ' . $niveau . 'ème étage';
        }
    }
];
        $pdf = Pdf::loadView('pdfs.bon_pre_reservation', $pdfData);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'isPhpEnabled' => true,
            'defaultFont' => 'dejavu sans',
        ]);

        return $pdf->download("bon_pre_reservation_{$data['code_pre_reservation']}.pdf");

    } catch (\Exception $e) {
        Log::error('Bon Pré Réservation PDF Generation Error: ' . $e->getMessage());
        return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
    }
}
// Fonction pour convertir l'abréviation de l'orientation en nom complet
private function getOrientationFullName($abbreviation)
{
    $orientationMap = [
        'N' => 'Nord',
        'S' => 'Sud',
        'E' => 'Est',
        'O' => 'Ouest',
        'N_E' => 'Nord-Est',
        'N_O' => 'Nord-Ouest',
        'S_E' => 'Sud-Est',
        'S_O' => 'Sud-Ouest',
        'NORD_SUD' => 'Nord/Sud',
        'NORD_OUEST' => 'Nord-Ouest',
        'SUD_EST' => 'Sud-Est',
        'EST_OUEST' => 'Est/Ouest',
        'NO_SE' => 'Nord-Ouest / Sud-Est',
        'NORD_SUD_OUEST' => 'Nord/Sud/Ouest',
        'NORD_SUD_EST' => 'Nord-Ouest / Sud-Est',
        'NORD_EST_OUEST' => 'Nord/Est/Ouest',

    ];
    return $orientationMap[$abbreviation] ?? ($abbreviation ?: ' ');
}

// Fonction pour convertir le niveau en texte
private function getNiveauText($niveau)
{
    if ($niveau === null || $niveau === '') {
        return 'au rez-de-chaussée';
    }

    $niveau = (int)$niveau;

    if ($niveau == 0) {
        return 'au rez-de-chaussée (RDC)';
    } elseif ($niveau == 1) {
        return 'au 1er étage';
    } else {
        return 'au ' . $niveau . 'ème étage';
    }
}
/*******************************************penalite */

public function generatePenalitePDF(Request $request)
{
    try {
        $data = $request->input('data');

        if (!$data) {
            return response()->json(['error' => 'No data provided'], 400);
        }

        // Process logo
        $logoBase64 = null;
        $user = $data['user'] ?? [];
        $societe = $user['societe'] ?? [];

        if (isset($societe['logo']) &&
            isset($societe['raison_sociale_concatene']) &&
            isset($societe['id'])) {

            $logoFilename = $societe['logo'];
            $logoPath = $societe['raison_sociale_concatene'] . '_' . $societe['id'] . '/logos/' . $logoFilename;

            $fileContent = null;

            if (app()->environment('production')) {
                if (Storage::disk('s3')->exists($logoPath)) {
                    $fileContent = Storage::disk('s3')->get($logoPath);
                }
            } else {
                $localPath = public_path('docs/' . $logoPath);
                if (file_exists($localPath)) {
                    $fileContent = file_get_contents($localPath);
                }
            }

            if ($fileContent !== null) {
                $extension = pathinfo($logoFilename, PATHINFO_EXTENSION);
                $mimeType = match($extension) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    default => 'image/png'
                };
                $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
            }
        }

        // Fonction pour obtenir le nom complet du bien
        $getBienInfo = function($bien) {
            if (!$bien) return '';
            $noms = [];
            if (isset($bien['tranche']['nom'])) $noms[] = $bien['tranche']['nom'];
            if (isset($bien['bloc']['nom'])) $noms[] = $bien['bloc']['nom'];
            if (isset($bien['immeuble']['nom'])) $noms[] = $bien['immeuble']['nom'];
            if (isset($bien['propriete_dite_bien'])) $noms[] = $bien['propriete_dite_bien'];
            return implode(' - ', $noms);
        };

        // Formater les clients
        $clients = $data['clients'] ?? [];
        $clientNames = '';
        $estMultiplesClients = false;

        if (is_array($clients)) {
            $flatClients = [];
            foreach ($clients as $client) {
                if (is_array($client)) {
                    $flatClients = array_merge($flatClients, $client);
                } else {
                    $flatClients[] = $client;
                }
            }
            if (count($flatClients) > 0) {
                $clientNames = implode(' ', $flatClients);
                $estMultiplesClients = count($flatClients) > 1;
            }
        } elseif (is_string($clients)) {
            $clientNames = $clients;
        }

        $pdfData = [
            'logoBase64' => $logoBase64,
            'societe' => $societe,
            'code_dossier' => $data['code_dossier'] ?? '',
            'num_recu' => $data['num_recu'] ?? '',
            'montant_penalite' => $data['montant_penalite'] ?? 0,
            'mode_paiement' => $data['mode_paiement'] ?? '',
            'numero_paiement' => $data['numero_paiement'] ?? '',
            'bien' => $data['bien'] ?? null,
            'bienCompletNom' => $getBienInfo($data['bien'] ?? null),
            'commercial_nom' => $data['commercial_nom'] ?? '',
            'commercial_prenom' => $data['commercial_prenom'] ?? '',
            'clientNames' => $clientNames,
            'estMultiplesClients' => $estMultiplesClients,
            'currentDate' => now()->format('d/m/Y'),
            'formatCurrency' => function($amount) {
                return number_format($amount, 0, ',', ' ') . ' DH';
            },
            'getModePaiementLabel' => function($mode) {
                switch ((int)$mode) {
                    case 1: return 'Espèces';
                    case 2: return 'Chèque';
                    case 3: return 'Chèque de banque';
                    case 4: return 'Chèque certifié';
                    case 5: return 'Virement bancaire';
                    case 6: return 'Versement';
                    default: return '';
                }
            }
        ];

        $pdf = Pdf::loadView('pdfs.penalite_desistement', $pdfData);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'isPhpEnabled' => true,
            'defaultFont' => 'dejavu sans',
        ]);

        return $pdf->download("recu_penalite_{$data['num_recu']}.pdf");

    } catch (\Exception $e) {
        Log::error('Penalite PDF Generation Error: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
    }
}

/*********************************RDVµµµµµµµµµµµµµµµµµµµµ */

public function generateRdvPDF(Request $request)
{
    try {
        $data = $request->input('data');

        if (!$data) {
            return response()->json(['error' => 'No data provided'], 400);
        }

        // Process logo
        $logoBase64 = null;
        $user = $data['user'] ?? [];
        $societe = $user['societe'] ?? [];

        if (isset($societe['logo']) &&
            isset($societe['raison_sociale_concatene']) &&
            isset($societe['id'])) {

            $logoFilename = $societe['logo'];
            $logoPath = $societe['raison_sociale_concatene'] . '_' . $societe['id'] . '/logos/' . $logoFilename;

            $fileContent = null;

            if (app()->environment('production')) {
                if (Storage::disk('s3')->exists($logoPath)) {
                    $fileContent = Storage::disk('s3')->get($logoPath);
                }
            } else {
                $localPath = public_path('docs/' . $logoPath);
                if (file_exists($localPath)) {
                    $fileContent = file_get_contents($localPath);
                }
            }

            if ($fileContent !== null) {
                $extension = pathinfo($logoFilename, PATHINFO_EXTENSION);
                $mimeType = match($extension) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    default => 'image/png'
                };
                $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
            }
        }

        $pdfData = [
            'logoBase64' => $logoBase64,
            'societe' => $societe,
            'code_reservation' => $data['code_reservation'] ?? '',
            'bien_propriete' => $data['bien_propriete'] ?? '',
            'type_rdv' => $data['type_rdv'] ?? '',
            'date_rdv' => $data['date_rdv'] ?? '',
            'num_recu' => $data['num_recu'] ?? '',
            'currentDate' => now()->format('d/m/Y'),
            'formatDate' => function($date) {
                if (!$date) return '';
                return \Carbon\Carbon::parse($date)->format('d/m/Y H:i');
            }
        ];

        $pdf = Pdf::loadView('pdfs.recu_rdv', $pdfData);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'isPhpEnabled' => true,
            'defaultFont' => 'dejavu sans',
        ]);

        return $pdf->download("recu_rdv_{$data['num_recu']}.pdf");

    } catch (\Exception $e) {
        Log::error('Rdv PDF Generation Error: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
    }
}

/*************************************Contraaaaat****************** */


public function generateContratVentePDF(Request $request)
{
    try {
        $data = $request->input('data');

        if (!$data) {
            return response()->json(['error' => 'No data provided'], 400);
        }

        // Process logo
        $logoBase64 = null;
        $societe = $data['societe'] ?? [];

        if (isset($societe['logo']) &&
            isset($societe['raison_sociale_concatene']) &&
            isset($societe['id'])) {

            $logoFilename = $societe['logo'];
            $logoPath = $societe['raison_sociale_concatene'] . '_' . $societe['id'] . '/logos/' . $logoFilename;

            $fileContent = null;

            if (app()->environment('production')) {
                if (Storage::disk('s3')->exists($logoPath)) {
                    $fileContent = Storage::disk('s3')->get($logoPath);
                }
            } else {
                $localPath = public_path('docs/' . $logoPath);
                if (file_exists($localPath)) {
                    $fileContent = file_get_contents($localPath);
                }
            }

            if ($fileContent !== null) {
                $extension = pathinfo($logoFilename, PATHINFO_EXTENSION);
                $mimeType = match($extension) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    default => 'image/png'
                };
                $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
            }
        }


        $reservation = $data['reservation'] ?? [];

        $pdfData = [
            'logoBase64' => $logoBase64,
            'societe' => $societe,
            'reservation' => $reservation,
            'date_sign_client' => $reservation['date_sign_client'] ?? null,
            'date_sign_mo' => $reservation['date_sign_mo'] ?? null,
            'date_enreg' => $reservation['date_enreg'] ?? null,
            'commentaire' => $reservation['commentaire'] ?? '',
            'sum_avances_valides' => $reservation['sum_avances_valides'] ?? 0,
            'num_recu' => $reservation['num_recu'] ?? 'temp',
            'currentDate' => now()->format('d/m/Y'),
            'formatDate' => function($date) {
                if (!$date) return '';
                return \Carbon\Carbon::parse($date)->format('d/m/Y');
            },
            'formatCivilite' => function($civilite) {
                switch ($civilite) {
                    case "1": return "Monsieur";
                    case "2": return "Madame";
                    case "3": return "Mademoiselle";
                    default: return "";
                }
            },
            'formatCurrency' => function($amount) {
                return number_format($amount, 0, ',', ' ') . ' DH';
            }
        ];

        $pdf = Pdf::loadView('pdfs.contrat_vente', $pdfData);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
            'isPhpEnabled' => true,
            'defaultFont' => 'dejavu sans',
        ]);

        return $pdf->download("contrat_vente_{$reservation['num_recu']}.pdf");

    } catch (\Exception $e) {
        Log::error('Contrat Vente PDF Generation Error: ' . $e->getMessage());
        Log::error('Stack trace: ' . $e->getTraceAsString());
        return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
    }
}

    /****************contrat de vente reservation**************************/

 public function generateContratVentePDF_reservation(Request $request)
    {
        try {
            $data = $request->input('data');

            if (!$data) {
                return response()->json(['error' => 'No data provided'], 400);
            }

            $societe = $data['societe'] ?? [];

            // Process logo (IMOZINE logo - left side)
            $logoBase64 = null;
            if (isset($societe['logo']) &&
                isset($societe['raison_sociale_concatene']) &&
                isset($societe['id'])) {

                $logoFilename = $societe['logo'];
                $logoPath = $societe['raison_sociale_concatene'] . '_' . $societe['id'] . '/logos/' . $logoFilename;

                $fileContent = null;

                if (app()->environment('production')) {
                    if (Storage::disk('s3')->exists($logoPath)) {
                        $fileContent = Storage::disk('s3')->get($logoPath);
                    }
                } else {
                    $localPath = public_path('docs/' . $logoPath);
                    if (file_exists($localPath)) {
                        $fileContent = file_get_contents($localPath);
                    }
                }

                if ($fileContent !== null) {
                    $extension = pathinfo($logoFilename, PATHINFO_EXTENSION);
                    $mimeType = match($extension) {
                        'png' => 'image/png',
                        'jpg', 'jpeg' => 'image/jpeg',
                        'gif' => 'image/gif',
                        'svg' => 'image/svg+xml',
                        default => 'image/png'
                    };
                    $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);
                }
            }

            // Try to load green_ang.png from logos folder
            $greenLandBase64 = null;
            if (isset($societe['raison_sociale_concatene']) && isset($societe['id'])) {
                $greenLandPath = $societe['raison_sociale_concatene'] . '_' . $societe['id'] . '/logos/green_land.png';
                $fileContent = null;

                if (app()->environment('production')) {
                    if (Storage::disk('s3')->exists($greenLandPath)) {
                        $fileContent = Storage::disk('s3')->get($greenLandPath);
                    }
                } else {
                    $localPath = public_path('docs/' . $greenLandPath);
                    if (file_exists($localPath)) {
                        $fileContent = file_get_contents($localPath);
                    }
                }

                if ($fileContent !== null) {
                    $greenLandBase64 = 'data:image/png;base64,' . base64_encode($fileContent);
                }
            }

            $reservation = $data['reservation'] ?? [];
            $bien = $reservation['bien'] ?? [];

            // Get total price from reservation
            $totalPrice = $reservation['prix'] ?? 0;

            // Get parking and box prices from BIEN
            $prixParking = $bien['prix_parking'] ?? 0;
            $prixBox = $bien['prix_box'] ?? 0;
            $nbParking = $bien['nb_parking'] ?? 0;
            $nbBox = $bien['nb_box'] ?? 0;

            // Calculate prices
            $parkingTotal = $prixParking * $nbParking;
            $boxTotal = $prixBox * $nbBox;
            $apartmentPrice = $totalPrice - $parkingTotal - $boxTotal;

            // Get surface areas from BIEN
            $surfaceParking = $bien['superficie_parking'] ?? 0;
            $surfaceBox = $bien['superficie_box'] ?? 0;
            $surfaceVendable = $bien['superficie_vendable'] ?? 0;

            // Payment schedule
            $paymentSchedule = $data['payment_schedule'] ?? [
                ['step' => 1, 'label' => 'À la signature (dépôt de garantie)', 'percentage' => 5],
                ['step' => 2, 'label' => 'Achèvement des fondations', 'percentage' => 15],
                ['step' => 3, 'label' => 'Achèvement du gros œuvre (toutes dalles)', 'percentage' => 25],
                ['step' => 4, 'label' => 'Achèvement maçonnerie et cloisonnement', 'percentage' => 20],
                ['step' => 5, 'label' => 'Achèvement corps d\'état secondaires', 'percentage' => 15],
                ['step' => 6, 'label' => 'Achèvement des travaux de finition', 'percentage' => 10],
                ['step' => 7, 'label' => 'Obtention du certificat de conformité', 'percentage' => 5],
                ['step' => 8, 'label' => 'Signature acte définitif — remise des clés', 'percentage' => 5],
            ];

            // Calculate payment amounts
            foreach ($paymentSchedule as &$schedule) {
                $schedule['amount'] = ($totalPrice * $schedule['percentage']) / 100;
            }

            // Prepare data for PDF
            $pdfData = [
                'logoBase64' => $logoBase64,
                'greenLandBase64' => $greenLandBase64,
                'societe' => $societe,
                'reservation' => $reservation,
                'bien' => $bien,
                'apartmentPrice' => $apartmentPrice,
                'parkingTotal' => $parkingTotal,
                'boxTotal' => $boxTotal,
                'totalPrice' => $totalPrice,
                'prixParking' => $prixParking,
                'prixBox' => $prixBox,
                'nbParking' => $nbParking,
                'nbBox' => $nbBox,
                'surfaceParking' => $surfaceParking,
                'surfaceBox' => $surfaceBox,
                'surfaceVendable' => $surfaceVendable,
                'sum_avances_valides' => $data['sum_avances_valides'] ?? 0,
                'num_recu' => $data['num_recu'] ?? 'temp',
                'currentDate' => now()->format('d/m/Y'),
                'paymentSchedule' => $paymentSchedule,
                'page' => 1,
                'totalPages' => 5,
                'formatDate' => function($date) {
                    if (!$date) return '';
                    return Carbon::parse($date)->format('d/m/Y');
                },
                'formatCivilite' => function($civilite) {
                    switch ($civilite) {
                        case "1": return "Monsieur";
                        case "2": return "Madame";
                        case "3": return "Mademoiselle";
                        default: return "Monsieur / Madame";
                    }
                },
                'formatCurrency' => function($amount) {
                    if ($amount <= 0) return '……… DH';
                    return number_format($amount, 0, ',', ' ') . ' DH';
                },
                'formatPercentage' => function($amount, $percentage) {
                    if ($amount <= 0) return '……… DH';
                    return number_format(($amount * $percentage / 100), 0, ',', ' ') . ' DH';
                }
            ];

            $pdf = Pdf::loadView('pdfs.contrat_vente_reservation', $pdfData);
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'isPhpEnabled' => true,
                'defaultFont' => 'dejavu sans',
                'margin_top' => 10,
                'margin_bottom' => 10,
                'margin_left' => 15,
                'margin_right' => 15,
            ]);

            // Fix: Use a variable for the filename
            $numRecu = $data['num_recu'] ?? 'temp';
            return $pdf->download("contrat_vente_{$numRecu}.pdf");

        } catch (\Exception $e) {
            Log::error('Contrat Vente PDF Generation Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to generate PDF: ' . $e->getMessage()], 500);
        }
    }




}
