<?php 
// src/Http/Controllers/TodoController.php
namespace CapsuleCmdr\SeatOsmm\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;
use CapsuleCmdr\SeatOsmm\Models\Todo;

class TodoController extends Controller
{
    // GET /osmm/todos  → list current user tasks (newest first)
    public function index(Request $request) {
        $items = Todo::where('user_id', Auth::id())
            ->orderByDesc('id')
            ->get(['id','text','created_at']);
        return response()->json($items);
    }

    // POST /osmm/todos  → create task { text: "..." }
    public function store(Request $request) {
        $data = $request->validate([
            'text' => 'required|string|min:1|max:200',
        ]);

        $todo = Todo::create([
            'user_id' => Auth::id(),
            'text'    => trim($data['text']),
        ]);

        return response()->json(['id'=>$todo->id,'text'=>$todo->text,'created_at'=>$todo->created_at], 201);
    }

    // DELETE /osmm/todos/{id} → hard delete (only own)
    public function destroy($id) {
        $todo = Todo::where('id', $id)->where('user_id', Auth::id())->first();
        if (! $todo) return response()->json([], 404);
        $todo->delete(); // hard delete
        return response()->json([], 204);
    }
}
