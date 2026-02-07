<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Get all notifications for a person using JOIN
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('notifications')
                ->join('persons', 'notifications.person_id', '=', 'persons.id')
                ->select(
                    'notifications.*',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.email'
                );

            // Filter by person
            if ($request->has('person_id')) {
                $query->where('notifications.person_id', $request->person_id);
            }

            // Filter by type
            if ($request->has('type')) {
                $query->where('notifications.type', $request->type);
            }

            // Filter by read status
            if ($request->has('read')) {
                $query->where('notifications.read', $request->boolean('read'));
            }

            // Order by
            $orderBy = $request->get('order_by', 'created_at');
            $orderDir = $request->get('order_dir', 'desc');
            $query->orderBy('notifications.' . $orderBy, $orderDir);

            $notifications = $query->get()->map(function($notification) {
                if ($notification->data) {
                    $notification->data = json_decode($notification->data, true);
                }
                return $notification;
            });

            return response()->json($notifications, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new notification
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'person_id' => 'required|exists:persons,id',
            'type' => 'required|in:info,success,warning,error,event,request,ticket',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'dashboard_link' => 'nullable|string|max:500',
            'data' => 'nullable|array',
            'email_sent' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $notificationId = DB::table('notifications')->insertGetId([
                'person_id' => $request->person_id,
                'type' => $request->type,
                'title' => $request->title,
                'message' => $request->message,
                'dashboard_link' => $request->dashboard_link,
                'data' => $request->data ? json_encode($request->data) : null,
                'read' => false,
                'email_sent' => $request->email_sent ?? false,
                'created_at' => now(),
            ]);

            $notification = DB::table('notifications')
                ->where('id', $notificationId)
                ->first();

            if ($notification->data) {
                $notification->data = json_decode($notification->data, true);
            }

            return response()->json([
                'message' => 'Notification créée avec succès',
                'notification' => $notification
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création de la notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific notification
     */
    public function show($id)
    {
        try {
            $notification = DB::table('notifications')
                ->join('persons', 'notifications.person_id', '=', 'persons.id')
                ->where('notifications.id', $id)
                ->select(
                    'notifications.*',
                    'persons.first_name',
                    'persons.last_name',
                    'persons.email'
                )
                ->first();

            if (!$notification) {
                return response()->json(['message' => 'Notification non trouvée'], 404);
            }

            if ($notification->data) {
                $notification->data = json_decode($notification->data, true);
            }

            return response()->json($notification, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        try {
            $exists = DB::table('notifications')->where('id', $id)->exists();
            if (!$exists) {
                return response()->json(['message' => 'Notification non trouvée'], 404);
            }

            DB::table('notifications')
                ->where('id', $id)
                ->update([
                    'read' => true,
                    'read_at' => now(),
                ]);

            $notification = DB::table('notifications')->where('id', $id)->first();

            return response()->json([
                'message' => 'Notification marquée comme lue',
                'notification' => $notification
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for a person
     */
    public function markAllAsRead($personId)
    {
        try {
            DB::table('notifications')
                ->where('person_id', $personId)
                ->where('read', false)
                ->update([
                    'read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'message' => 'Toutes les notifications ont été marquées comme lues'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy($id)
    {
        try {
            $exists = DB::table('notifications')->where('id', $id)->exists();
            if (!$exists) {
                return response()->json(['message' => 'Notification non trouvée'], 404);
            }

            DB::table('notifications')->where('id', $id)->delete();

            return response()->json(['message' => 'Notification supprimée avec succès'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all read notifications for a person
     */
    public function deleteAllRead($personId)
    {
        try {
            DB::table('notifications')
                ->where('person_id', $personId)
                ->where('read', true)
                ->delete();

            return response()->json([
                'message' => 'Toutes les notifications lues ont été supprimées'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread count for a person
     */
    public function getUnreadCount($personId)
    {
        try {
            $count = DB::table('notifications')
                ->where('person_id', $personId)
                ->where('read', false)
                ->count();

            return response()->json(['unread_count' => $count], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors du comptage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notification statistics for a person
     */
    public function getStats($personId)
    {
        try {
            $stats = [
                'total_notifications' => DB::table('notifications')->where('person_id', $personId)->count(),
                'unread_notifications' => DB::table('notifications')->where('person_id', $personId)->where('read', false)->count(),
                'read_notifications' => DB::table('notifications')->where('person_id', $personId)->where('read', true)->count(),
                'by_type' => DB::table('notifications')
                    ->where('person_id', $personId)
                    ->select('type', DB::raw('count(*) as count'))
                    ->groupBy('type')
                    ->get()
                    ->pluck('count', 'type')
            ];

            return response()->json($stats, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk create notifications (for sending to multiple users)
     */
    public function bulkCreate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'person_ids' => 'required|array',
            'person_ids.*' => 'exists:persons,id',
            'type' => 'required|in:info,success,warning,error,event,request,ticket',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'dashboard_link' => 'nullable|string|max:500',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $notifications = [];
            foreach ($request->person_ids as $personId) {
                $notifications[] = [
                    'person_id' => $personId,
                    'type' => $request->type,
                    'title' => $request->title,
                    'message' => $request->message,
                    'dashboard_link' => $request->dashboard_link,
                    'data' => $request->data ? json_encode($request->data) : null,
                    'read' => false,
                    'email_sent' => false,
                    'created_at' => now(),
                ];
            }

            DB::table('notifications')->insert($notifications);

            return response()->json([
                'message' => 'Notifications créées avec succès',
                'count' => count($notifications)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de la création des notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}