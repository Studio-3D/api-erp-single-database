<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\DatabaseHelper;
use App\Http\Helpers\RoleHelper;
use App\Http\Requests\StoreTypeBienRequest;
use App\Http\Requests\UpdateTypeBienRequest;
use App\Models\TypeBien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TypeBienController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function get_typeBiens()
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();

            $typebiens = TypeBien::on('temp')->orderBy('created_at', 'desc')->get();
            return response()->json(['typeBiens' => $typebiens]);
        }

        return response()->json(['error' => 'Unauthorized'], 401);

    }

    public function get_typeBiensByProjet($projet_id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();

            $typebiens = TypeBien::on('temp')
            ->orderBy('created_at', 'desc')
            ->where('projet_id', $projet_id)
            ->get();
            return response()->json(['typeBiens' => $typebiens]);
        }

        return response()->json(['error' => 'Unauthorized'], 401);

    }

    public function index(Request $request)
    {
        if (Auth::guard('api')->check()) {
            $size = $request->input('size', config('app.default_item_number_perpage'));
            $page = $request->input('page', 1);
            $projet_id = $request->input('projet_id');
            DatabaseHelper::Config();

            $query = TypeBien::on('temp');

            if ($projet_id) {
                $query->where('projet_id', $projet_id);
            }

            if ($request->filled('type')) {
                $query->where('type', 'like', '%' . $request->input('type') . '%');
            }

            $typeBiens = $query->orderBy('created_at', 'desc')
                ->paginate($size, ['*'], 'page', $page);

            $pagination = [
                'currentPage' => $typeBiens->currentPage(),
                'totalItems' => $typeBiens->total(),
                'totalPages' => $typeBiens->lastPage(),
            ];

            $typeBiens = $typeBiens->items();

            return response()->json([
                'typeBiens' => $typeBiens,
                'pagination' => $pagination,
            ], 200);
        }

        return response()->json(['error' => 'Unauthorized'], 401);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTypeBienRequest $request)
    {
        if (RoleHelper::AdminSup()) {

            DatabaseHelper::Config();
            $typebien = new typebien();
            $typebien->setConnection('temp');
            $typebien->type = $request->type;
            $typebien->projet_id = $request->projet_id;
            $typebien->save();
            return response()->json(['message' => $typebien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (Auth::guard('api')->check()) {
            DatabaseHelper::Config();
            $typebien = typebien::on('temp')->where('id', $id)->first();

            return response()->json(['typeBien' => $typebien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $typebien = typebien::on('temp')->findOrfail($id);
            return response()->json(['message' => $typebien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTypeBienRequest $request, $id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $typebien = typebien::on('temp')->findOrfail($id);
            $update = $request->all();
            foreach ($update as $key => $value) {
                $typebien->$key = $value;
            }
            $typebien->save();

            return response()->json(['message' => $typebien], 200);
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $typebien = typebien::on('temp')->findOrfail($id);

            if ($typebien->delete()) {
                return response()->json(['message' => 'ce type de bien deleted succesfully'], 200);
            } else {
                return response()->json(['message' => 'ce type de bien non deleted'], 404);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);

        }
    }

    public function restoreTypeBien($typeBien_id)
    {
        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            TypeBien::on('temp')->where('id', $typeBien_id)->withTrashed()->restore();

            return response()->json(['message' => 'Type Bien est bien restaurer'], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public function getTrashedTypesBien()
    {

        if (RoleHelper::AdminSup()) {
            DatabaseHelper::Config();
            $typeBiens = TypeBien::on('temp')->onlyTrashed()->get();

            return response()->json(['typeBien' => $typeBiens], 200);

        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    public static function AjouterTypeBien($typeBien, $projet_id)
    {

            $typeBienController = new TypeBienController();
            $typeBienRequest = new StoreTypeBienRequest;
                $dataTypebien = [
                    'type' => $typeBien,
                    'projet_id' => $projet_id,
                ];
                $typeBienRequest->merge($dataTypebien);
                $typeBienController->store($typeBienRequest);



    }



}
