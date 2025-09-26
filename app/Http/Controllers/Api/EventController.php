<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\EventChangeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $start = $request->query('start');
        $end   = $request->query('end');
        $query = $request->query('q');
        $divisionIds = Arr::wrap($request->query('division'));

        // Normal (non-recurring) events overlapping range
        $events = Event::query()
            ->when($start && $end, fn($q) => $q->whereNull('recurrence_type')->overlap($start, $end))
            ->when($query, function ($q) use ($query) {
                $q->where(function ($w) use ($query) {
                    $w->where('title', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%")
                      ->orWhere('location', 'like', "%{$query}%");
                });
            })
            ->when(!empty($divisionIds), function ($q) use ($divisionIds) {
                $q->where(function ($w) use ($divisionIds) {
                    $w->whereHas('participantUsers', function ($p) use ($divisionIds) {
                        $p->whereIn('division_id', $divisionIds);
                    })->orWhereHas('divisions', function ($d) use ($divisionIds) {
                        $d->whereIn('divisions.id', $divisionIds);
                    });
                });
            })
            ->with(['participantUsers:id,name,division_id', 'participantUsers.division:id,name', 'divisions:id,name'])
            ->orderBy('start_at')
            ->get();

        // Recurring series (expanded later)
        $recurring = Event::query()
            ->whereNotNull('recurrence_type')
            ->when($query, function ($q) use ($query) {
                $q->where(function ($w) use ($query) {
                    $w->where('title', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%")
                      ->orWhere('location', 'like', "%{$query}%");
                });
            })
            ->when(!empty($divisionIds), function ($q) use ($divisionIds) {
                $q->where(function ($w) use ($divisionIds) {
                    $w->whereHas('participantUsers', function ($p) use ($divisionIds) {
                        $p->whereIn('division_id', $divisionIds);
                    })->orWhereHas('divisions', function ($d) use ($divisionIds) {
                        $d->whereIn('divisions.id', $divisionIds);
                    });
                });
            })
            ->with(['participantUsers:id,name,division_id', 'participantUsers.division:id,name', 'divisions:id,name'])
            ->get();

        $expanded = [];
        if ($start && $end) {
            $rangeStart = \Illuminate\Support\Carbon::parse($start)->startOfDay();
            $rangeEnd = \Illuminate\Support\Carbon::parse($end)->endOfDay();
            if ($rangeEnd->lt($rangeStart)) {
                [$rangeStart, $rangeEnd] = [$rangeEnd, $rangeStart];
            }
            foreach ($recurring as $e) {
                $until = $e->recurrence_until ? \Illuminate\Support\Carbon::parse($e->recurrence_until)->endOfDay() : $rangeEnd;
                $until = $until->min($rangeEnd);
                $anchor = $e->start_at->copy();
                $interval = (int)($e->recurrence_rule['interval'] ?? 1);
                if ($interval < 1) $interval = 1;
                if ($e->recurrence_type === 'weekly') {
                    $days = collect($e->recurrence_rule['days'] ?? [])->map(fn($d)=>(int)$d)->filter(fn($d)=>$d>=1 && $d<=7)->values()->all();
                    if (empty($days)) continue;
                    $week = $rangeStart->copy()->startOfWeek(1);
                    $anchorWeek = $anchor->copy()->startOfWeek(1);
                    if ($week->lt($anchorWeek)) {
                        $week = $anchorWeek->copy();
                    }
                    // align to interval from anchor week
                    $weeksBetween = $anchorWeek->diffInWeeks($week, false);
                    $mod = ($weeksBetween % $interval + $interval) % $interval;
                    if ($mod !== 0) {
                        $week = $week->addWeeks($interval - $mod);
                    }
                    $guard = 0;
                    while ($week->lte($until)) {
                        foreach ($days as $dow) {
                            $occDate = $week->copy()->startOfWeek(1)->addDays($dow-1);
                            if ($occDate->lt($anchor->copy()->startOfDay())) continue;
                            if ($occDate->lt($rangeStart) || $occDate->gt($rangeEnd) || $occDate->gt($until)) continue;
                            // Set start time from anchor (local H:i:s) safely
                            $h = (int)$anchor->format('H');
                            $m = (int)$anchor->format('i');
                            $s = (int)$anchor->format('s');
                            $occStart = $occDate->copy()->setTime($h, $m, $s);
                            // Robust duration calculation (positive seconds)
                            $dur = $e->end_at->getTimestamp() - $e->start_at->getTimestamp();
                            if ($dur <= 0) { $dur = abs($dur); if ($dur === 0) { $dur = 60; } }
                            $occEnd = $occStart->copy()->addSeconds($dur);
                            $expanded[] = [
                                'id' => $e->id,
                                'title' => $e->title,
                                'description' => $e->description,
                                'location' => $e->location,
                                'start_at' => $occStart->toIso8601String(),
                                'end_at' => $occEnd->toIso8601String(),
                                'participant_summary' => $e->participant_summary,
                                'all_day' => (bool)$e->all_day,
                                'divisions' => $e->divisions->map(fn($d)=>['id'=>$d->id,'name'=>$d->name])->all(),
                                'participants' => $e->participantUsers->map(fn($u)=>[
                                    'id'=>$u->id,'name'=>$u->name,
                                    'division' => $u->relationLoaded('division') && $u->division ? ['id'=>$u->division->id,'name'=>$u->division->name] : null,
                                ])->all(),
                                'occurrence_key' => $e->id.'-'.$occStart->timestamp,
                            ];
                        }
                        $week = $week->addWeeks($interval);
                        if ((++$guard) > 520) { // hard guard: ~10 years of weekly expansions
                            break;
                        }
                    }
                }
                if ($e->recurrence_type === 'monthly') {
                    $monthDays = collect($e->recurrence_rule['month_days'] ?? [])->map(fn($d)=>(int)$d)->filter(fn($d)=>$d>=1 && $d<=31)->values()->all();
                    if (empty($monthDays)) continue;
                    $cursor = $rangeStart->copy()->startOfMonth();
                    $anchorMonth = $anchor->copy()->startOfMonth();
                    if ($cursor->lt($anchorMonth)) {
                        $cursor = $anchorMonth->copy();
                    }
                    $monthsBetween = $anchorMonth->diffInMonths($cursor, false);
                    $mod = ($monthsBetween % $interval + $interval) % $interval;
                    if ($mod !== 0) {
                        $cursor = $cursor->addMonths($interval - $mod);
                    }
                    $guard = 0;
                    while ($cursor->lte($until)) {
                        $daysInMonth = $cursor->daysInMonth;
                        foreach ($monthDays as $md) {
                            if ($md > $daysInMonth) continue;
                            $occDate = $cursor->copy()->setDay($md);
                            if ($occDate->lt($anchor->copy()->startOfDay())) continue;
                            if ($occDate->lt($rangeStart) || $occDate->gt($rangeEnd) || $occDate->gt($until)) continue;
                            $h = (int)$anchor->format('H');
                            $m = (int)$anchor->format('i');
                            $s = (int)$anchor->format('s');
                            $occStart = $occDate->copy()->setTime($h, $m, $s);
                            $dur = $e->end_at->getTimestamp() - $e->start_at->getTimestamp();
                            if ($dur <= 0) { $dur = abs($dur); if ($dur === 0) { $dur = 60; } }
                            $occEnd = $occStart->copy()->addSeconds($dur);
                            $expanded[] = [
                                'id' => $e->id,
                                'title' => $e->title,
                                'description' => $e->description,
                                'location' => $e->location,
                                'start_at' => $occStart->toIso8601String(),
                                'end_at' => $occEnd->toIso8601String(),
                                'participant_summary' => $e->participant_summary,
                                'all_day' => (bool)$e->all_day,
                                'divisions' => $e->divisions->map(fn($d)=>['id'=>$d->id,'name'=>$d->name])->all(),
                                'participants' => $e->participantUsers->map(fn($u)=>[
                                    'id'=>$u->id,'name'=>$u->name,
                                    'division' => $u->relationLoaded('division') && $u->division ? ['id'=>$u->division->id,'name'=>$u->division->name] : null,
                                ])->all(),
                                'occurrence_key' => $e->id.'-'.$occStart->timestamp,
                            ];
                        }
                        $cursor = $cursor->addMonths($interval);
                        if ((++$guard) > 240) { // hard guard: 20 years of monthly expansions
                            break;
                        }
                    }
                }
            }
        }

        // Serialize normal events with the existing resource
        $normal = EventResource::collection($events)->resolve();
        $merged = array_merge($normal, $expanded);
        usort($merged, fn($a,$b) => strcmp($a['start_at'], $b['start_at']));

        return response()->json(['data' => $merged]);
    }

    public function store(StoreEventRequest $request)
    {
        $this->authorize('create', Event::class);

        $data = $request->validated();
        $event = new Event();
        $payload = Arr::only($data, ['title','description','participant_summary','location','start_at','end_at','all_day']);
        $summary = isset($payload['participant_summary']) ? trim((string) $payload['participant_summary']) : null;
        $payload['participant_summary'] = $summary !== '' ? $summary : null;
        $event->fill($payload);
        // Recurrence (store)
        if (!empty($data['recurrence_type'])) {
            $event->recurrence_type = $data['recurrence_type'];
            $rule = [ 'interval' => max(1, (int)($data['recurrence_interval'] ?? 1)) ];
            if ($data['recurrence_type'] === 'weekly') {
                $rule['days'] = array_values(array_unique(array_map('intval', $data['recurrence_days'] ?? [])));
            }
            if ($data['recurrence_type'] === 'monthly') {
                $rule['month_days'] = array_values(array_unique(array_map('intval', $data['recurrence_month_days'] ?? [])));
            }
            $event->recurrence_rule = $rule;
            if (!empty($data['recurrence_until'])) $event->recurrence_until = $data['recurrence_until'];
        }
        $event->created_by = $request->user()?->id;
        $event->save();

        if (!empty($data['participant_user_ids'])) {
            $sync = collect($data['participant_user_ids'])->mapWithKeys(fn($id) => [$id => ['participant_role' => null]])->all();
            $event->participantUsers()->sync($sync);
        }

        if (!empty($data['division_ids'])) {
            $event->divisions()->sync($data['division_ids']);
        }

        // Log create
        if (Schema::hasTable('event_change_logs')) {
            $divisionIds = array_values($data['division_ids'] ?? []);
            $participantIds = array_values($data['participant_user_ids'] ?? []);
            $changes = [
                'title' => [null, $event->title],
                'description' => [null, $event->description],
                'participant_summary' => [null, $event->participant_summary],
                'location' => [null, $event->location],
                'start_at' => [null, optional($event->start_at)->toIso8601String()],
                'end_at' => [null, optional($event->end_at)->toIso8601String()],
                'all_day' => [null, (bool)$event->all_day],
                'recurrence_type' => [null, $event->recurrence_type],
                'recurrence_rule' => [null, $event->recurrence_rule],
                'recurrence_until' => [null, $event->recurrence_until?->toDateString()],
                'division_ids' => [[], $divisionIds],
                'participant_user_ids' => [[], $participantIds],
            ];
            EventChangeLog::query()->create([
                'event_id' => $event->id,
                'actor_id' => $request->user()?->id,
                'action' => 'create_event',
                'changes' => $changes,
            ]);
        }

        return new EventResource($event->load(['participantUsers:id,name,division_id', 'participantUsers.division:id,name', 'divisions:id,name']));
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        $this->authorize('update', $event);

        $data = $request->validated();
        // Keep originals for diff
        $original = $event->only(['title','description','participant_summary','location','start_at','end_at','all_day','recurrence_type','recurrence_rule','recurrence_until']);
        $origDivIds = $event->divisions()->pluck('divisions.id')->all();
        $origPartIds = $event->participantUsers()->pluck('users.id')->all();
        $payload = Arr::only($data, ['title','description','participant_summary','location','start_at','end_at','all_day']);
        if (array_key_exists('participant_summary', $payload)) {
            $summary = trim((string) ($payload['participant_summary'] ?? ''));
            $payload['participant_summary'] = $summary !== '' ? $summary : null;
        }
        $event->fill($payload);
        // Recurrence (update)
        if ($request->has('recurrence_type')) {
            $event->recurrence_type = $data['recurrence_type'];
        }
        if ($event->recurrence_type) {
            $rule = [ 'interval' => max(1, (int)($data['recurrence_interval'] ?? ($event->recurrence_rule['interval'] ?? 1))) ];
            if ($event->recurrence_type === 'weekly') {
                $rule['days'] = array_values(array_unique(array_map('intval', $data['recurrence_days'] ?? ($event->recurrence_rule['days'] ?? []))));
            }
            if ($event->recurrence_type === 'monthly') {
                $rule['month_days'] = array_values(array_unique(array_map('intval', $data['recurrence_month_days'] ?? ($event->recurrence_rule['month_days'] ?? []))));
            }
            $event->recurrence_rule = $rule;
            if ($request->has('recurrence_until')) $event->recurrence_until = $data['recurrence_until'];
        } else {
            $event->recurrence_rule = null;
            $event->recurrence_until = null;
        }
        $event->save();

        if ($request->has('participant_user_ids')) {
            $sync = collect($data['participant_user_ids'] ?? [])->mapWithKeys(fn($id) => [$id => ['participant_role' => null]])->all();
            $event->participantUsers()->sync($sync);
        }

        if ($request->has('division_ids')) {
            $event->divisions()->sync($data['division_ids'] ?? []);
        }

        // Log update
        if (Schema::hasTable('event_change_logs')) {
            $now = $event->only(['title','description','participant_summary','location','start_at','end_at','all_day','recurrence_type','recurrence_rule','recurrence_until']);
            // normalize datetimes/booleans
            $normalize = function ($k, $v) {
                if ($k === 'start_at' || $k === 'end_at') return optional($v)->toIso8601String();
                if ($k === 'recurrence_until') return $v ? (string) $v : null;
                if ($k === 'all_day') return (bool) $v;
                return $v;
            };
            $changes = [];
            foreach ($now as $k => $v) {
                $old = $original[$k] ?? null;
                $nv = $normalize($k, $v);
                $ov = $normalize($k, $old);
                if ($ov !== $nv) $changes[$k] = [$ov, $nv];
            }
            $newDivIds = $event->divisions()->pluck('divisions.id')->all();
            $newPartIds = $event->participantUsers()->pluck('users.id')->all();
            sort($origDivIds); sort($newDivIds); sort($origPartIds); sort($newPartIds);
            if ($origDivIds !== $newDivIds) $changes['division_ids'] = [$origDivIds, $newDivIds];
            if ($origPartIds !== $newPartIds) $changes['participant_user_ids'] = [$origPartIds, $newPartIds];
            if (!empty($changes)) {
                EventChangeLog::query()->create([
                    'event_id' => $event->id,
                    'actor_id' => $request->user()?->id,
                    'action' => 'update_event',
                    'changes' => $changes,
                ]);
            }
        }

        return new EventResource($event->load(['participantUsers:id,name,division_id', 'participantUsers.division:id,name', 'divisions:id,name']));
    }

    public function destroy(Request $request, Event $event)
    {
        $this->authorize('delete', $event);

        // snapshot before delete
        $snapshot = [
            'title' => $event->title,
            'description' => $event->description,
              'participant_summary' => $event->participant_summary,
            'location' => $event->location,
            'start_at' => optional($event->start_at)->toIso8601String(),
            'end_at' => optional($event->end_at)->toIso8601String(),
            'all_day' => (bool)$event->all_day,
            'recurrence_type' => $event->recurrence_type,
            'recurrence_rule' => $event->recurrence_rule,
            'recurrence_until' => $event->recurrence_until?->toDateString(),
            'division_ids' => $event->divisions()->pluck('divisions.id')->all(),
            'participant_user_ids' => $event->participantUsers()->pluck('users.id')->all(),
        ];

        // Log BEFORE deleting so FK is valid; ON DELETE SET NULL will nullify event_id after deletion
        if (Schema::hasTable('event_change_logs')) {
            $changes = [];
            foreach ($snapshot as $k => $v) {
                $changes[$k] = [$v, null];
            }
            EventChangeLog::query()->create([
                'event_id' => $event->id,
                'actor_id' => $request->user()?->id,
                'action' => 'delete_event',
                'changes' => $changes,
            ]);
        }

        $event->delete();

        return response()->noContent();
    }
}







