<?php
/**
 * Jano Ticketing System
 * Copyright (C) 2019 Andrew Ying and other contributors.
 *
 * This file is part of Jano Ticketing System.
 *
 * Jano Ticketing System is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License
 * v3.0 supplemented by additional permissions and terms as published at
 * COPYING.md.
 *
 * Jano Ticketing System is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this program. If not, see
 * <http://www.gnu.org/licenses/>.
 */

namespace Jano\Repositories;

use DB;
use Illuminate\Support\Collection;
use Jano\Contracts\AttendeeContract;
use Jano\Contracts\ChargeContract;
use Jano\Contracts\TicketContract;
use Jano\Events\AttendeeDestroyed;
use Jano\Events\AttendeesCreated;
use Jano\Models\Attendee;
use Jano\Models\Ticket;
use Jano\Models\User;
use Jano\Notifications\AttendeesCreated as AttendeesCreatedNotification;
use Jano\Repositories\HelperRepository as Helper;

class AttendeeRepository implements AttendeeContract
{
    /**
     * @var \Jano\Contracts\TicketContract
     */
    protected $ticket;

    /**
     * @var \Jano\Contracts\ChargeContract
     */
    protected $charge;

    /**
     * AttendeeRepository constructor.
     *
     * @param \Jano\Contracts\TicketContract $ticket
     * @param \Jano\Contracts\ChargeContract $charge
     */
    public function __construct(TicketContract $ticket, ChargeContract $charge)
    {
        $this->ticket = $ticket;
        $this->charge = $charge;
    }

    /**
     * @inheritdoc
     */
    public function store(
        User $user,
        Collection $attendees,
        $frontend = true
    ) {
        $tickets = Ticket::all();
        $account = $user->account()->first();

        if ($frontend) {
            $amount = 0;

            foreach ($tickets as $ticket_type) {
                $amount += Helper::getUserPrice($ticket_type->price, $user, false) *
                    $attendees->where('ticket_id', $ticket_type['id'])->count();
            }

            DB::beginTransaction();

            $charge_created = $this->charge->store(
                $account,
                [
                    'amount' => $amount,
                    'description' => trans_choice(
                        'system.ticket_order_for_attendee',
                        $attendees->count(),
                        ['count' => $attendees->count()]
                    )
                ]
            );

            $account->amount_due += $amount;
            $account->save();
        } else {
            DB::beginTransaction();

            $charge_created = $this->charge->store($account, [
                'amount' => 0,
                'description' => trans_choice(
                    'system.ticket_order_for_attendee',
                    $attendees->count(),
                    ['count' => $attendees->count()]
                )
            ]);
        }

        $return = collect();

        foreach ($attendees as $attendee) {
            $ticket = $tickets->where('id', $attendee['ticket']['id'])->first();
            $return->push(
                $this->ticket->reserve(
                    [
                        'ticket' => $ticket,
                        'user' => $user,
                        'charge' => $charge_created,
                        'data' => $attendee
                    ],
                    $frontend
                )
            );
        }

        DB::commit();

        if ($frontend) {
            $user->notify(new AttendeesCreatedNotification($account, $return));
        }
        event(new AttendeesCreated($user, $return));

        return $return;
    }

    /**
     * @inheritdoc
     */
    public function index()
    {
        return Attendee::with('ticket')
            ->get();
    }

    /**
     * @inheritdoc
     */
    public function search($query)
    {
        $query = $query ? '%' . $query . '%' : '%';

        return Attendee::where('first_name', 'like', $query)
            ->orWhere('last_name', 'like', $query)
            ->orWhere('email', 'like', $query)
            ->withTrashed()
            ->with('ticket')
            ->paginate();
    }

    /**
     * @inheritdoc
     */
    public function update(Attendee $attendee, $data)
    {
        foreach ($data as $attribute => $value) {
            $attendee->{$attribute} = $value;
        }
        $attendee->save();

        return $attendee;
    }

    /**
     * @inheritdoc
     */
    public function export(array $fields = [])
    {
        if (empty($fields)) {
            $fields = Attendee::getAttributeListing();
        }

        $attendees = Attendee::with('ticket')->get();

        $array = array();
        foreach ($attendees as $attendee) {
            $row = array();
            foreach ($fields as $field) {
                if ($field === 'ticket') {
                    $row[] = $attendee->ticket->name;
                } else {
                    $row[] = $attendee->{$field};
                }
            }

            $array[] = $row;
        }

        return $array;
    }

    /**
     * @inheritdoc
     */
    public function destroy($attendee)
    {
        if (is_a($attendee, Collection::class)) {
            $attendee->each(function ($item) {
                $item->delete();
            });
        } else {
            $attendee->delete();
        }

        event(new AttendeeDestroyed($attendee));
    }
}
