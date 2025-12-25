<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Spending;
use App\Models\SpendingType;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use Symfony\Component\HttpFoundation\Response;
class SpendingController extends Controller
{
    private $key;

    public function __construct()
    {
        $this->key = env('JWT_SECRET', 'your-256-bit-secret');
    }

    public function index(Request $request)
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));

            if (!property_exists($decoded, 'user')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $userId = $decoded->user->id;

            $spendings = Spending::where('user_id', $userId)->with('spendingType')->get();

            return response()->json($spendings, Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function types()
    {
        try {
            $types = SpendingType::all();
            return response()->json($types, Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric',
                'description' => 'required|string|max:255',
                'spending_type_id' => 'required|exists:spending_types,id',
            ]);

            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));

            if (!property_exists($decoded, 'user')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $userId = $decoded->user->id;

            $spending = Spending::create([
                'user_id' => $userId,
                'amount' => $request->input('amount'),
                'description' => $request->input('description'),
                'spending_type_id' => $request->input('spending_type_id'),
            ]);

            return response()->json([
                'message' => 'Spending recorded successfully',
                'spending' => $spending->with('spendingType')->find($spending->id),
            ], Response::HTTP_CREATED);
        } catch (ValidationException $ve) {
            return response()->json(['error' => $ve->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function todaySpendings(Request $request)
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));

            if (!property_exists($decoded, 'user')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $userId = $decoded->user->id;

            $today = date('Y-m-d');

            $spendings = Spending::where('user_id', $userId)
                ->whereDate('created_at', $today)
                ->with('spendingType')
                ->get();

            $total = $spendings->sum('amount');

            return response()->json([
                'spendings' => $spendings,
                'total' => $total
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    
    public function thisMonthDailyTotals(Request $request)
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));

            if (!property_exists($decoded, 'user')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $userId = $decoded->user->id;

            $currentMonth = date('m');
            $currentYear = date('Y');

            $dailyTotals = Spending::selectRaw('DATE(created_at) as date, SUM(amount) as total_amount')
                ->where('user_id', $userId)
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->groupBy('date')
                ->orderBy('date', 'ASC')
                ->get();

            return response()->json($dailyTotals, Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function thisMonthEachSpendingTypeTotals(Request $request)
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));

            if (!property_exists($decoded, 'user')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $userId = $decoded->user->id;

            $currentMonth = date('m');
            $currentYear = date('Y');

            $typeTotals = Spending::selectRaw('spending_type_id, SUM(amount) as total_amount')
                ->where('user_id', $userId)
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->groupBy('spending_type_id')
                ->with('spendingType')
                ->get();

            return response()->json($typeTotals, Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function thisMonthTotal(Request $request)
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            $decoded = JWT::decode($token, new Key($this->key, 'HS256'));

            if (!property_exists($decoded, 'user')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $userId = $decoded->user->id;

            $currentMonth = date('m');
            $currentYear = date('Y');

            $total = Spending::where('user_id', $userId)
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->sum('amount');

            return response()->json(['total_amount' => $total], Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
}