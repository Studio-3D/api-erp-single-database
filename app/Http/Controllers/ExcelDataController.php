<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Tranche;
use App\Models\Bien;
use App\Models\Bloc;
use App\Models\CompositionBien; 
use App\Models\TypeBien;
use App\Models\Immeuble;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingcolumn;
use Illuminate\Support\Str;
use App\Http\Helpers\Bien_Helper;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\ImportExcelHelper;
use App\Models\Projet;


class ExcelDataController extends Controller{

   
        
        
        
        

       
    public function UploadDataExcel(Request $request){
        
        
        $projet_id = $request->projetId;

        DatabaseHelper::Config();

        $projet = Projet::on('temp')->findOrFail($projet_id);

        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $data = $request->input('data');

        $keys = array_keys($data[0]);
        
   
        

        $importMethod = $this->determineImportMethod($keys);

        return ImportExcelHelper::$importMethod($data, $projet_id);
    }
    
        
        private function determineImportMethod($keys) {
            $hasTranche = in_array('tranche', $keys);
            $hasBloc = in_array('Bloc', $keys);
            $hasImmeuble = in_array('immeuble', $keys);
        
            if ($hasTranche && $hasBloc && $hasImmeuble) {
                return 'ImportStockByProjet';
           

            } elseif ($hasTranche && $hasBloc && !$hasImmeuble) {
         

                return 'ImportStockByProjetWithoutImmeuble';
            } elseif ($hasTranche && !$hasBloc && $hasImmeuble) {
          

                return 'ImportStockByProjetWithoutBloc';
            } elseif ($hasTranche && !$hasBloc && !$hasImmeuble) {

                return 'ImportStockByProjetWithoutBlocAndImmeuble';
            } elseif (!$hasTranche && $hasBloc && $hasImmeuble) {

                return 'ImportStockByProjetWithoutTranche';
            } elseif (!$hasTranche && $hasBloc && !$hasImmeuble) {

                return 'ImportStockByProjetWithoutTrancheAndImmeuble';
            } elseif (!$hasTranche && !$hasBloc && $hasImmeuble) {

                return 'ImportStockByProjetWithoutTrancheAndBloc';
            } else {
                return 'ImportStockByProjetWithoutTrancheAndBlocAndImmeuble';
            }
        }
    }

           


    


