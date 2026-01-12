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
use App\Models\Rating;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;



class AdminController extends Controller
{
    /**
     * GET /api/admin/services
     * Query params: service_type (consultation, home_visit, delivery), page, per_page
     * Returns completed services with low ratings (<= 2 stars)
     */
    public function services(Request $request)
    {
        $request->validate([
            'service_type' => 'required|in:consultation,home_visit,delivery',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $serviceType = $request->query('service_type');
        $perPage = (int) $request->query('per_page', 10);

        if ($serviceType === 'consultation') {
            $query = Rating::query()
                ->where('target_type', 'doctor')
                ->whereNotNull('consultation_id')
                ->where('stars', '<=', 2)
                ->join('consultations', 'consultations.id', '=', 'ratings.consultation_id')
                ->join('doctors', 'doctors.id', '=', 'ratings.target_id')
                ->join('users as doctor_users', 'doctor_users.id', '=', 'doctors.user_id')
                ->join('patients', 'patients.id', '=', 'consultations.patient_id')
                ->join('users as patient_users', 'patient_users.id', '=', 'patients.user_id')
                ->where('consultations.status', 'completed')
                ->select([
                    'ratings.id as rating_id',
                    'ratings.stars',
                    'consultations.id as service_id',
                    'consultations.updated_at as completed_at',
                    'patient_users.full_name as patient_name',
                    'patient_users.phone as patient_phone',
                    'doctor_users.full_name as provider_name',
                ])
                ->orderByDesc('ratings.created_at');

            $paginated = $query->paginate($perPage, ['*'], 'page', (int) $request->query('page', 1));

            $data = collect($paginated->items())->map(function ($r) {
                return [
                    'id' => $r->service_id,
                    'patient_name' => $r->patient_name,
                    'patient_phone' => $r->patient_phone,
                    'service_name' => 'Consultation',
                    'service_provider' => $r->provider_name,
                    'provider_type' => 'doctor',
                    'rating' => (int) $r->stars,
                    'date' => optional($r->completed_at)->toDateString(),
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'last_page' => $paginated->lastPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        if ($serviceType === 'home_visit') {
            $query = Rating::query()
                ->where('target_type', 'care_provider')
                ->whereNotNull('home_visit_id')
                ->where('stars', '<=', 2)
                ->join('home_visits', 'home_visits.id', '=', 'ratings.home_visit_id')
                ->join('care_providers', 'care_providers.id', '=', 'ratings.target_id')
                ->join('users as provider_users', 'provider_users.id', '=', 'care_providers.user_id')
                ->join('patients', 'patients.id', '=', 'home_visits.patient_id')
                ->join('users as patient_users', 'patient_users.id', '=', 'patients.user_id')
                ->where('home_visits.status', 'completed')
                ->select([
                    'ratings.id as rating_id',
                    'ratings.stars',
                    'home_visits.id as service_id',
                    'home_visits.ended_at as completed_at',
                    'patient_users.full_name as patient_name',
                    'patient_users.phone as patient_phone',
                    'provider_users.full_name as provider_name',
                    'care_providers.type as provider_type',
                ])
                ->orderByDesc('ratings.created_at');

            $paginated = $query->paginate($perPage, ['*'], 'page', (int) $request->query('page', 1));

            $data = collect($paginated->items())->map(function ($r) {
                return [
                    'id' => $r->service_id,
                    'patient_name' => $r->patient_name,
                    'patient_phone' => $r->patient_phone,
                    'service_name' => 'Home Visit - ' . ($r->provider_type === 'nurse' ? 'Nurse' : 'Physiotherapist'),
                    'service_provider' => $r->provider_name,
                    'provider_type' => 'care_provider',
                    'rating' => (int) $r->stars,
                    'date' => optional($r->completed_at)->toDateString(),
                ];
            })->values();

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'last_page' => $paginated->lastPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        // delivery
        $query = Rating::query()
            ->where('target_type', 'delivery')
            ->whereNotNull('delivery_task_id')
            ->where('stars', '<=', 2)
            ->join('delivery_tasks', 'delivery_tasks.id', '=', 'ratings.delivery_task_id')
            ->join('orders', 'orders.id', '=', 'delivery_tasks.order_id')
            ->join('patients', 'patients.id', '=', 'orders.patient_id')
            ->join('users as patient_users', 'patient_users.id', '=', 'patients.user_id')
            ->join('deliveries', 'deliveries.id', '=', 'ratings.target_id')
            ->join('users as delivery_users', 'delivery_users.id', '=', 'deliveries.user_id')
            ->whereNotNull('delivery_tasks.delivered_at')
            ->select([
                'ratings.id as rating_id',
                'ratings.stars',
                'orders.id as service_id',
                'delivery_tasks.delivered_at as completed_at',
                'patient_users.full_name as patient_name',
                'patient_users.phone as patient_phone',
                'delivery_users.full_name as provider_name',
            ])
            ->orderByDesc('ratings.created_at');

        $paginated = $query->paginate($perPage, ['*'], 'page', (int) $request->query('page', 1));

        $data = collect($paginated->items())->map(function ($r) {
            return [
                'id' => $r->service_id,
                'patient_name' => $r->patient_name,
                'patient_phone' => $r->patient_phone,
                'service_name' => 'Medication Delivery',
                'service_provider' => $r->provider_name,
                'provider_type' => 'delivery',
                'rating' => (int) $r->stars,
                'date' => optional($r->completed_at)->toDateString(),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'last_page' => $paginated->lastPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }
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
				'revenue' => [//
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
        // سجل الأدمن اللي وافق
        $user->admin_note = 'Approved by admin ID: ' . Auth::id();
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
        // سجل الأدمن اللي رفض
        $user->admin_note = 'Rejected by admin ID: ' . Auth::id();
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Account rejected',
            'rejection_reason' => $user->rejection_reason,
        ]);
    }

/**
 * PUT /api/admin/users/{id}/edit
 * Editable Fields: full_name, email, phone
 */
public function editUser(Request $request, $id)
{
    $request->validate([
        'full_name' => 'sometimes|string|max:255',
        'email' => 'sometimes|email|unique:users,email,' . $id,
        'phone' => 'sometimes|string|max:20',
    ]);

    $user = User::find($id);
    if (!$user) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not found'
        ], 404);
    }

    $user->fill($request->only(['full_name', 'email', 'phone']));
    $user->save();

    return response()->json([
        'status' => 'success',
        'message' => 'Account updated successfully',
        'user' => [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]
    ]);
}


/**
 * DELETE /api/admin/users/{id}/delete
 */
public function deleteUser($id)
{
    $user = User::find($id);
    if (!$user) {
        return response()->json([
            'status' => 'error',
            'message' => 'User not found'
        ], 404);
    }

    $user->delete();

    return response()->json([
        'status' => 'success',
        'message' => 'Account deleted successfully',
    ]);
}

	
}