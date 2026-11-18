<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlanController extends Controller
{
    /**
     * Public list of subscription plans for the pricing page and sign-up.
     */
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(Plan::active()->get());
    }
}
