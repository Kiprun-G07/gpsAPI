<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Mail;

class EventController extends Controller
{
    private $key;

    public function __construct()
    {
        $this->key = env('JWT_SECRET', 'your-256-bit-secret');
    }

    public function index(Request $request)
    {
        return response()->json(Event::all(), 200);
    }

    public function show($id)
    {
        $event = Event::find($id);
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }
        return response()->json($event, 200);
    }

    public function attend(Request $request, $id)
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

            $event = Event::find($id);
            if (!$event) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            $existingAttendance = DB::table('event_attendees')
                ->where('event_id', $id)
                ->where('user_id', $userId)
                ->first();

            if ($existingAttendance) {
                return response()->json(['error' => 'Already registered for this event'], 400);
            }

            DB::table('event_attendees')->insert([
                'event_id' => $id,
                'user_id' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            Mail::to($decoded->user->email)->send(new \App\Mail\EventJoinMail($decoded->user, $event));

            return response()->json(['message' => 'Successfully registered for the event'], 200);
        } catch (Exception $e) {
            Log::error('Error in attend method: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }

    public function assignCrewMember(Request $request, $id)
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

            $event = Event::find($id);
            if (!$event) {
                return response()->json(['error' => 'Event not found'], 404);
            }

            $existingCrewMember = DB::table('event_crew_members')
                ->where('event_id', $id)
                ->where('user_id', $userId)
                ->first();

            if ($existingCrewMember) {
                return response()->json(['error' => 'Already a crew member for this event'], 400);
            }

            // $request->validate([
            //     'role' => 'sometimes|string|in:member,leader'
            // ]);

            DB::table('event_crew_members')->insert([
                'event_id' => $id,
                'user_id' => $userId,
                'role' => $request->role,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            Mail::to($decoded->user->email)->send(new \App\Mail\EventCrewJoinMail($decoded->user, $event));

            return response()->json(['message' => 'Successfully assigned as crew member for the event'], 200);
        } catch (Exception $e) {
            Log::error('Error in assignCrewMember method: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }

    public function checkIfJoinedAsCrewOrAttendee(Request $request, $id)
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

            $isCrewMember = $this->checkIfCrewMember($id, $userId);
            $isAttendee = $this->checkIfAttendee($id, $userId);

            return response()->json([
                'is_crew_member' => $isCrewMember,
                'is_attendee' => $isAttendee,
                'is_either' => $isCrewMember || $isAttendee
            ], 200);
        } catch (Exception $e) {
            Log::error('Error in checkIfJoinedAsCrewOrAttendee method: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }

    public function checkIfCrewMember($eventId, $userId)
    {
        $crewMember = DB::table('event_crew_members')
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->first();

        return $crewMember !== null;
    }

    public function checkIfAttendee($eventId, $userId)
    {
        $attendee = DB::table('event_attendees')
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->first();

        return $attendee !== null;
    }

    public function create(Request $request)
    {
        $request->validate([
            'event_name' => 'required|string|max:255',
            'event_date' => 'required|date',
            'event_location' => 'required|string|max:255',
            'event_description' => 'nullable|string',
            'max_participants' => 'nullable|integer|min:1',
        ]);

        $event = Event::create($request->all());

        return response()->json([
            'message' => 'Event created successfully',
            'event' => $event
        ], 201);
    }
    
    public function update(Request $request, $id)
    {
        $event = Event::find($id);
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        $request->validate([
            'event_name' => 'sometimes|string|max:255',
            'event_date' => 'sometimes|date',
            'event_location' => 'sometimes|string|max:255',
            'event_description' => 'nullable|string',
            'max_participants' => 'nullable|integer|min:1',
        ]);

        $event->update($request->all());

        return response()->json([
            'message' => 'Event updated successfully',
            'event' => $event
        ], 200);
    }   

    public function delete($id)
    {
        $event = Event::find($id);
        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully'], 200);
    }

    public function listAttendeesForAllEvents(Request $request)
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
            $attendees = DB::table('event_attendees')
                ->join('events', 'event_attendees.event_id', '=', 'events.id')
                ->join('users', 'event_attendees.user_id', '=', 'users.id')
                ->select('events.id', 'events.event_name', 'users.name', 'users.email')
                ->get();

            return response()->json($attendees, 200);
        } catch (Exception $e) {
            Log::error('Error in listAttendeesForAllEvents method: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred'], 500);
        }
    }
}   

?>