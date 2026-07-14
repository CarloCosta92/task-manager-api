<?php

namespace App\Http\Controllers;

use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;

use function Laravel\Prompts\title;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // 1. prendi l'utente autenticato
        $user = auth('api')->user();

        // 2. costruisci la query di base (usa tasks() con parentesi, non la proprietà,
        //    perché ora dobbiamo aggiungere condizioni prima di eseguirla)

        /** @var \App\Models\User $user */
        $user = auth('api')->user();
        $query = $user->tasks();

        // 3. applica il filtro "completed" se presente nella query string
        //    hint: $request->has('completed') per controllare se il parametro esiste
        //    hint: $query->where('status', $request->boolean('completed'))

        if ($request->has('completed')) {
            $query->where('status', $request->boolean('completed'));
        }


        // 4. applica il filtro "search" se presente
        //    hint: $request->has('search')
        //    hint: $query->where('title', 'like', '%' . $request->search . '%')

        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }


        // 5. pagina i risultati invece di ->get()
        //    hint: $query->paginate(10)
        $tasks = $query->paginate(10);

        // 6. return response()->json(...) con TaskResource::collection

        return response()->json([
            "status" => "success",
            "data" => TaskResource::collection($tasks),
            "meta" => [
                "current_page" => $tasks->currentPage(),
                "last_page" => $tasks->lastPage(),
                "per_page" => $tasks->perPage(),
                "total" => $tasks->total(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. valida i dati in input (title obbligatorio, description opzionale)
        $validated = $request->validate([
            'title' => 'required',
            'description' => 'nullable',

        ]);
        // 2. crea il task collegato all'utente autenticato
        //    usa la relazione tasks() così user_id viene settato automaticamente
        /** @var \App\Models\User $user */
        $user = auth('api')->user();
        $task = $user->tasks()->create($validated);
        // 3. return response()->json(...) con status 201 (created) e il task creato
        return response()->json([
            "status" => "success",
            "message" => "Task creato con successo",
            "data" => new TaskResource($task),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        $this->authorize('view', $task);

        // 2. return response()->json(...) con il task
        return response()->json([
            "status" => "success",
            "data" => new TaskResource($task),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Task $task)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => 'sometimes',
            'description' => 'sometimes',
        ]);

        $task->update($validated);

        return response()->json([
            "status" => "success",
            "data" => new TaskResource($task),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json([
            "status" => "success",
            "message" => "Task eliminato con successo",
        ]);
    }
}
