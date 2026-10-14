export type Room = { id: number; number: string; type: string; rate: string; capacity: number; status: string }
export type Guest = { id: number; name: string; email: string; phone: string | null }
export type Reservation = { id: number; guest_id: number; room_id: number; check_in: string; check_out: string; status: string; total_amount: string; notes: string | null; guest: Guest; room: Room }
export type Stats = { rooms: number; guests: number; active_reservations: number; occupied_rooms: number; arrivals_today: number }
export type User = { id: number; name: string; email: string }
