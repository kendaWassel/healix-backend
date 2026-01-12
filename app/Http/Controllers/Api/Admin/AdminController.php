<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Pharmacist;
use App\Models\CareProvider;
use App\Models\Delivery;
use App\Models\DeliveryTask;
use App\Models\Consultation;
use App\Models\Order;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\Schema;


class AdminController extends Controller
{
	/**
	 * GET /api/admin/dashboard
	 * Returns aggregated statistics for admin dashboard
	 */
	public function dashboard(Request $request)
	{
		// Users
		$patients = Patient::count();
		$doctors = Doctor::count();
		$pharmacists = Pharmacist::count();
		$nurses = CareProvider::where('type', 'nurse')->count();
		$physiotherapists = CareProvider::where('type', 'physiotherapist')->count();
		$deliveryAgents = Delivery::count();

		// Consultations
		$consultationsTotal = Consultation::count();
		$consultationsCompleted = Consultation::where('status', 'completed')->count();
		$consultationsCancelled = Consultation::where('status', 'cancelled')->count();

		// Orders
		$ordersTotal = Order::count();
		// delivered info is stored on delivery tasks (delivery_tasks.delivered_at)
		$ordersDelivered = DeliveryTask::whereNotNull('delivered_at')->count();
		$ordersPending = Order::where('status', 'pending')->count();

		// Revenue (sum of order total_amount)
		$revenueTotal = (int) Order::sum('total_amount');

		// Pending documents (uploads with category 'document')
		$pendingDocuments = Upload::where('category', 'document')->count();

		// Top providers (doctors by consultations count)
		$topProviders = Doctor::with('user')
			->withCount('consultations')
			->orderByDesc('consultations_count')
			->take(5)
			->get()
			->map(function ($d) {
				return [
					'id' => $d->id,
					'name' => optional($d->user)->full_name ?? 'N/A',
					'total_consultations' => $d->consultations_count,
				];
			})->values();

		return response()->json([
			'status' => 'success',
			'data' => [
				'users' => [
					'patients' => $patients,
					'doctors' => $doctors,
					'pharmacists' => $pharmacists,
					'nurse' => $nurses,
					'physiotherapist' => $physiotherapists,
					'delivery_agents' => $deliveryAgents,
				],
				'consultations' => [
					'total' => $consultationsTotal,
					'completed' => $consultationsCompleted,
					'cancelled' => $consultationsCancelled,
				],
				'orders' => [
					'total' => $ordersTotal,
					'delivered' => $ordersDelivered,
					'pending' => $ordersPending,
				],
				'revenue' => [
					'total' => $revenueTotal,
					'currency' => 'SYP',
				],
				'pending_documents' => $pendingDocuments,
				'top_providers' => $topProviders,
			],
		]);
	}

	/**
	 * GET /api/admin/users
	 * Query: role, status, page, per_page
	 */
	public function users(Request $request)
	{
		$roleFilter = $request->query('role');
		$statusFilter = $request->query('status');
		$perPage = (int) $request->query('per_page', 10);

		$query = User::query()->with(['doctor', 'pharmacist', 'careProvider', 'delivery']);

		// Role filter
		if ($roleFilter) {
			if (in_array($roleFilter, ['doctor', 'pharmacist', 'delivery', 'patient', 'admin'])) {
				$query->where('role', $roleFilter === 'nurse' ? 'care_provider' : $roleFilter);
			} elseif (in_array($roleFilter, ['nurse', 'physiotherapist'])) {
				// care_provider types
				$query->where('role', 'care_provider')
					  ->whereHas('careProvider', function ($q) use ($roleFilter) {
						  $q->where('type', $roleFilter);
					  });
			}
		}

		// Status filter: prefer explicit `status` column if exists, otherwise use email verification
		if ($statusFilter) {
			if (Schema::hasColumn('users', 'status')) {
				$query->where('status', $statusFilter);
			} else {
				if ($statusFilter === 'activated') {
					$query->whereNotNull('email_verified_at');
				} elseif ($statusFilter === 'pending') {
					$query->whereNull('email_verified_at');
				} else {
					// unknown status mapping; no results
					$query->whereRaw('0 = 1');
				}
			}
		}

		$usersPaginated = $query->orderByDesc('created_at')->paginate($perPage);

		$data = $usersPaginated->getCollection()->map(function ($user) {
			$userType = 'patient';
			if ($user->doctor) $userType = 'doctor';
			elseif ($user->pharmacist) $userType = 'pharmacist';
			elseif ($user->delivery) $userType = 'delivery';
			elseif ($user->careProvider) $userType = $user->careProvider->type ?? 'care_provider';

			// determine attachment id based on role/profile
			$attachment = null;
			if ($userType === 'doctor' && $user->doctor->certificate_file_id) {
				$attachment = Upload::find($user->doctor->certificate_file_id);
			} elseif ($userType === 'pharmacist' && $user->pharmacist->license_file_id) {
				$attachment = Upload::find($user->pharmacist->license_file_id);
			} elseif (in_array($userType, ['nurse','physiotherapist']) && $user->careProvider->license_file_id) {
				$attachment = Upload::find($user->careProvider->license_file_id);
			} elseif ($userType === 'delivery' && $user->delivery->driving_license_id) {
				$attachment = Upload::find($user->delivery->driving_license_id);
			} else {
				// fallback: latest upload for user
				$attachment = Upload::where('user_id', $user->id)->latest()->first();
			}

			$attachmentData = null;
			if ($attachment) {
				$attachmentData = [
					'id' => $attachment->id,
					'file_url' => $attachment->file_path ? asset('storage/' . $attachment->file_path) : null,
				];
			}

			$status = null;
			if (Schema::hasColumn('users', 'status')) {
				$status = $user->status;
			} else {
				$status = $user->hasVerifiedEmail() ? 'activated' : 'pending';
			}

			return [
				'id' => $user->id,
				'full_name' => $user->full_name,
				'email' => $user->email,
				'user_type' => $userType,
				'status' => $status,
				'created_at' => optional($user->created_at)->toDateString(),
				'attachment' => $attachmentData,
			];
		})->values();

		return response()->json([
			'status' => 'success',
			'data' => $data,
			'meta' => [
				'current_page' => $usersPaginated->currentPage(),
				'per_page' => $usersPaginated->perPage(),
				'last_page' => $usersPaginated->lastPage(),
				'total' => $usersPaginated->total(),
			],
		]);
	}

	/**
	 * GET /api/admin/users/{user_id}/attachments
	 * Retrieve uploads for a given user
	 */
	public function attachments(Request $request, $user_id)
	{
		$user = User::find($user_id);
		if (!$user) {
			return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
		}

		$uploads = Upload::where('user_id', $user->id)->orderByDesc('created_at')->get();

		$data = $uploads->map(function ($u) {
			$fileName = $u->file ?? ($u->file_path ? basename($u->file_path) : null);
			return [
				'id' => $u->id,
				'file_name' => $fileName,
				'category' => $u->category,
				'file_url' => $u->file_path ? asset('storage/' . $u->file_path) : null,
				'uploaded_at' => optional($u->created_at)->toDateString(),
			];
		})->values();

		return response()->json([
			'status' => 'success',
			'data' => $data,
		]);
	}

	/**
 * PATCH /api/admin/users/{id}/approve
 * Approve & activate account
 */
public function approveUser($id)
{
	$user = User::find($id);

	if (!$user) {
		return response()->json([
			'status' => 'error',
			'message' => 'User not found'
		], 404);
	}

	if ($user->status === 'approved') {
		return response()->json([
			'status' => 'success',
			'message' => 'Account already approved'
		]);
	}

	$user->status = 'approved';
	$user->is_active = true;
	$user->approved_at = now();
	$user->rejection_reason = null;
	$user->save();

	return response()->json([
		'status' => 'success',
		'message' => 'Account approved and activated'
	]);
}
/**
 * PATCH /api/admin/users/{id}/reject
 * Reject account with reason
 */
public function rejectUser(Request $request, $id)
{
	$request->validate([
		'reason' => 'required|string|max:255',
	]);

	$user = User::find($id);

	if (!$user) {
		return response()->json([
			'status' => 'error',
			'message' => 'User not found'
		], 404);
	}

	$user->status = 'rejected';
	$user->is_active = false;
	$user->approved_at = null;
	$user->rejection_reason = $request->reason;
	$user->save();

	return response()->json([
		'status' => 'success',
		'message' => 'Account rejected'
	]);
}

	
}