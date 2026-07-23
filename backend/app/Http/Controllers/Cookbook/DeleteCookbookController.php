<?php

namespace App\Http\Controllers\Cookbook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cookbook\DeleteCookbookRequest;
use App\Models\Cookbook;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DeleteCookbookController extends Controller
{
    public function __invoke(DeleteCookbookRequest $request, Cookbook $cookbook): Response
    {
        DB::transaction(function () use ($cookbook): void {
            $cookbook->delete();
        });

        return response()->noContent();
    }
}
