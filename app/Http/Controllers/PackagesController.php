<?php

namespace App\Http\Controllers;

use App\Models\Packages;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PackagesController extends Controller
{
    public function index()
    {
        try {
            $packages = Packages::orderBy('name', 'ASC')->paginate(10);

            if ($packages->isEmpty()) {
                return response()->json(['message' => 'No package(s) found'], 404);
            }

            return response()->json($packages, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:packages,name'],
            'speed' => ['required', 'numeric', 'min:1'], // Mbps
            'price' => ['required', 'numeric', 'min:0'],
            'validity' => ['required', 'integer', 'min:1'], // days
            'dataLimit' => ['nullable', 'numeric', 'min:0'], // null = unlimited
            'isActive' => ['nullable', 'boolean'],
            'description' => ['required', 'string'],
            'devices' => ['nullable', 'numeric', 'min:1']
        ], [
            'name.required' => 'Package name is required.',
            'name.unique' => 'A package with this name already exists.',

            'speed.required' => 'Speed is required.',
            'speed.numeric' => 'Speed must be a number (Mbps).',

            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a valid number.',

            'validity.required' => 'Package validity is required.',
            'validity.integer' => 'Validity must be in days.',

            'dataLimit.numeric' => 'Data limit must be a number.',

            'description.required' => 'Description is required.',
        ]);

        DB::beginTransaction();
        try {

            $isActive = $request->boolean('isActive');

            Packages::create([
                'name' => $request->name,
                'speed' => $request->speed,
                'price' => $request->price,
                'dataLimit' => $request->dataLimit ?  $request->dataLimit : "Unlimited Data",
                'isActive' => $isActive,
                'validity' => $request->validity,
                'description' => $request->description,
                'devices' => $request->devices ? $request->devices : "Unlimited",
            ]);

            DB::commit();

            return response()->json(['message' => 'Package Added successfully'], 200);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function view($id)
    {
        try {
            $package = Packages::where('id', $id)->first();

            if (!$package) {
                return response()->json(['message' => 'Package not found'], 404);
            }
            return response()->json($package, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:packages,name,' . $id],
            'speed' => ['required', 'numeric', 'min:1'], // Mbps
            'price' => ['required', 'numeric', 'min:0'],
            'validity' => ['required', 'integer', 'min:1'], // days
            'dataLimit' => ['nullable', 'numeric', 'min:0'], // null = unlimited
            'isActive' => ['nullable', 'boolean'],
            'description' => ['required', 'string'],
            'devices' => ['nullable', 'numeric', 'min:1']
        ], [
            'name.required' => 'Package name is required.',
            'name.unique' => 'A package with this name already exists.',

            'speed.required' => 'Speed is required.',
            'speed.numeric' => 'Speed must be a number (Mbps).',

            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a valid number.',

            'validity.required' => 'Package validity is required.',
            'validity.integer' => 'Validity must be in days.',

            'dataLimit.numeric' => 'Data limit must be a number.',

            'description.required' => 'Description is required.',
        ]);

        DB::beginTransaction();

        try {
            $package = Packages::find($id);

            if (!$package) {
                return response()->json(['message' => 'Package not found'], 404);
            }

            // Fill package with request data
            $package->fill([
                'name' => $request->name,
                'speed' => $request->speed,
                'price' => $request->price,
                'validity' => $request->validity,
                'dataLimit' => $request->dataLimit ?  $request->dataLimit : "Unlimited Data",
                'isActive' => $request->isActive ?? 0, // default false if not set
                'description' => $request->description,
                'devices' => $request->devices ? $request->devices : "Unlimited",
            ]);

            if ($package->isDirty()) {
                $package->save();
                DB::commit();
                return response()->json(['message' => 'Package updated successfully'], 200);
            }

            return response()->json(['message' => 'No changes were detected'], 200);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $package = Packages::find($id);

            if (!$package) {
                return response()->json(['message' => 'Package not found'], 404);
            }

            // Determine new status: either from request or toggle current
            $newStatus = $request->has('isActive')
                ? $request->boolean('isActive')
                : !$package->isActive;

            if ($newStatus !== $package->isActive) {
                $package->isActive = $newStatus;
                $package->save();

                DB::commit();

                return response()->json([
                    'message' => 'Package status updated successfully',
                    'isActive' => $package->isActive
                ], 200);
            }

            return response()->json(['message' => 'No changes were detected'], 200);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $package = Packages::find($id);

            if (!$package) {
                return response()->json(['message' => 'Package not found'], 404);
            }

            $package->delete();

            return response()->json(['message' => 'Package deleted successfully'], 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }
}
