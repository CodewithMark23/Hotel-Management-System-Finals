import { useEffect, useState } from 'react'
import type { FormEvent } from 'react'
import { api, getToken, setToken } from './lib/api'
import type { Room, User } from './types'

type Page = 'home' | 'roomlist' | 'add-room' | 'room-details'

function getRoute(): { page: Page; roomId: number | null } {
  const hash = window.location.hash.slice(1)
  const [page, id] = hash.split('/')
  if (page === 'roomlist' || page === 'add-room' || page === 'room-details') {
    return {
      page,
      roomId: page === 'room-details' && /^\d+$/.test(id || '') ? Number(id) : null,
    }
  }
  return { page: 'home', roomId: null }
}

const money = (value: string) => new Intl.NumberFormat('en-PH', {
  style: 'currency', currency: import.meta.env.VITE_CURRENCY || 'PHP',
}).format(Number(value))

export default function HotelApp() {
  const [authenticated, setAuthenticated] = useState(!!getToken())
  const [user, setUser] = useState<User | null>(null)
  const [route, setRoute] = useState(getRoute)
  const [rooms, setRooms] = useState<Room[]>([])
  const [stats, setStats] = useState<{ rooms: number; guests: number; active_reservations: number; occupied_rooms: number; arrivals_today: number } | null>(null)
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState(false)

  useEffect(() => {
    const navigate = () => {
      setRoute(getRoute())
      setEditing(false)
      setError('')
    }
    const reset = () => {
      setAuthenticated(false)
      setUser(null)
      setRooms([])
      setEditing(false)
    }
    window.addEventListener('hashchange', navigate)
    window.addEventListener('hotel:unauthorized', reset)
    return () => {
      window.removeEventListener('hashchange', navigate)
      window.removeEventListener('hotel:unauthorized', reset)
    }
  }, [])

  async function refresh() {
    setLoading(true)
    setError('')
    try {
      const [roomResponse, userResponse, dashResponse] = await Promise.all([
        api<{ data: Room[] }>('/rooms'),
        api<{ data: User }>('/me'),
        api<{ data: { rooms: number; guests: number; active_reservations: number; occupied_rooms: number; arrivals_today: number } }>('/dashboard').catch(() => null),
      ])
      setRooms(roomResponse.data)
      setUser(userResponse.data)
      if (dashResponse?.data) setStats(dashResponse.data)
    } catch (e) {
      setError((e as Error).message)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => { if (authenticated) void refresh() }, [authenticated])

  async function login(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setError('')
    const fields = Object.fromEntries(new FormData(event.currentTarget))
    try {
      const result = await api<{ token: string; user: User }>('/login', {
        method: 'POST', body: JSON.stringify(fields),
      })
      setToken(result.token)
      setUser(result.user)
      window.location.hash = 'home'
      setAuthenticated(true)
    } catch (e) {
      setError((e as Error).message)
    } finally {
      setSaving(false)
    }
  }

  async function logout() {
    setSaving(true)
    setError('')
    try {
      await api('/logout', { method: 'POST' })
      setToken(null)
      setAuthenticated(false)
      setUser(null)
      setRooms([])
      setEditing(false)
      window.location.hash = ''
    } catch (e) {
      setError((e as Error).message)
    } finally {
      setSaving(false)
    }
  }

  async function saveRoom(event: FormEvent<HTMLFormElement>, room?: Room) {
    event.preventDefault()
    setSaving(true)
    setError('')
    const fields = Object.fromEntries(new FormData(event.currentTarget))
    try {
      const result = await api<{ data: Room }>(room ? `/rooms/${room.id}` : '/rooms', {
        method: room ? 'PUT' : 'POST', body: JSON.stringify(fields),
      })
      setRooms(current => [...current.filter(item => item.id !== result.data.id), result.data]
        .sort((a, b) => a.number.localeCompare(b.number)))
      setEditing(false)
      window.location.hash = room ? `room-details/${room.id}` : 'roomlist'
    } catch (e) {
      setError((e as Error).message)
    } finally {
      setSaving(false)
    }
  }

  async function deleteRoom(room: Room) {
    if (!window.confirm(`Delete room ${room.number}?`)) return
    setSaving(true)
    setError('')
    try {
      await api(`/rooms/${room.id}`, { method: 'DELETE' })
      setRooms(current => current.filter(item => item.id !== room.id))
      window.location.hash = 'roomlist'
    } catch (e) {
      setError((e as Error).message)
    } finally {
      setSaving(false)
    }
  }

  if (!authenticated) return <main className="login-page">
    <h1>Hotel Management System</h1>
    <section className="login-panel">
      <form onSubmit={login}>
        <h2>Admin Login</h2>
        {error && <div className="alert" role="alert">{error}</div>}
        <label>Email<input name="email" type="email" autoComplete="username" defaultValue="admin@hotel.test" required /></label>
        <label>Password<input name="password" type="password" autoComplete="current-password" required /></label>
        <button disabled={saving}>{saving ? 'Signing in...' : 'Sign in'}</button>
        <p className="demo">Local demo: admin@hotel.test / password</p>
      </form>
    </section>
  </main>

  const selectedRoom = rooms.find(room => room.id === route.roomId)
  const filteredRooms = rooms.filter(room =>
    `${room.number} ${room.type} ${room.status}`.toLowerCase().includes(search.toLowerCase()))
  const links: { page: Page; label: string }[] = [
    { page: 'home', label: 'Home' },
    { page: 'roomlist', label: 'Roomlist' },
    { page: 'add-room', label: 'Add Room' },
    { page: 'room-details', label: 'Room Details' },
  ]

  return <div className="app-shell">
    <h1>Hotel Management System</h1>
    <nav className="navbar" aria-label="Main navigation">
      {links.map(link => <a
        key={link.page}
        href={`#${link.page}`}
        className={route.page === link.page ? 'active' : ''}
        aria-current={route.page === link.page ? 'page' : undefined}
      >{link.label}</a>)}
      <button className="logout-button" onClick={() => void logout()} disabled={saving}>Logout</button>
    </nav>
    <p>Logged in as {user?.name || 'Admin'}</p>
    <main className="workspace">
      <header>
        <h2>{links.find(link => link.page === route.page)?.label}</h2>
        <button disabled={loading || saving} onClick={() => void refresh()}>Refresh</button>
      </header>
      {error && <div className="alert" role="alert">{error}</div>}
      {loading && <p role="status">Loading...</p>}

      {route.page === 'home' && <section className="editor">
        <h3>Welcome, {user?.name || 'Hotel Admin'}</h3>
        <p>This is the hotel management dashboard. Use the navigation links above or the quick actions below to manage rooms.</p>
        
        {stats && <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))', gap: '10px', margin: '15px 0' }}>
          <div style={{ border: '1px solid #aaa', padding: '10px', background: '#fafafa' }}>
            <strong>Total Rooms</strong>
            <div style={{ fontSize: '22px', marginTop: '5px' }}>{stats.rooms}</div>
          </div>
          <div style={{ border: '1px solid #aaa', padding: '10px', background: '#fafafa' }}>
            <strong>Occupied</strong>
            <div style={{ fontSize: '22px', marginTop: '5px' }}>{stats.occupied_rooms}</div>
          </div>
          <div style={{ border: '1px solid #aaa', padding: '10px', background: '#fafafa' }}>
            <strong>Active Bookings</strong>
            <div style={{ fontSize: '22px', marginTop: '5px' }}>{stats.active_reservations}</div>
          </div>
          <div style={{ border: '1px solid #aaa', padding: '10px', background: '#fafafa' }}>
            <strong>Total Guests</strong>
            <div style={{ fontSize: '22px', marginTop: '5px' }}>{stats.guests}</div>
          </div>
        </div>}

        <div className="actions" style={{ marginTop: '15px' }}>
          <a href="#roomlist"><button type="button">View Roomlist</button></a>
          <a href="#add-room"><button type="button">Add New Room</button></a>
        </div>
      </section>}

      {route.page === 'roomlist' && <>
        <label>Search rooms<input className="search" type="search" value={search} onChange={event => setSearch(event.target.value)} /></label>
        <div className="table-scroll">
          <table>
            <thead><tr><th>Room</th><th>Type</th><th>Nightly Rate</th><th>Capacity</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              {filteredRooms.map(room => <tr key={room.id}>
                <td>{room.number}</td>
                <td className="capitalize">{room.type}</td>
                <td>{money(room.rate)}</td>
                <td>{room.capacity}</td>
                <td>{room.status === 'available' ? 'In service' : 'Maintenance'}</td>
                <td><a href={`#room-details/${room.id}`}>View Details</a></td>
              </tr>)}
              {!loading && !filteredRooms.length && <tr><td colSpan={6}>No rooms found.</td></tr>}
            </tbody>
          </table>
        </div>
        <a className="add-room-action" href="#add-room" aria-label="Add Room" title="Add Room">+</a>
      </>}

      {route.page === 'add-room' && <section className="editor">
        <RoomForm onSubmit={event => void saveRoom(event)} saving={saving} />
        <p><a href="#roomlist">Back to Roomlist</a></p>
      </section>}

      {route.page === 'room-details' && <>
        <label>Select room
          <select value={route.roomId ?? ''} onChange={event => {
            window.location.hash = event.target.value ? `room-details/${event.target.value}` : 'room-details'
          }}>
            <option value="">Select a room</option>
            {rooms.map(room => <option key={room.id} value={room.id}>Room {room.number}</option>)}
          </select>
        </label>
        {selectedRoom ? <section className="editor">
          {editing ? <>
            <h3>Edit Room {selectedRoom.number}</h3>
            <RoomForm key={selectedRoom.id} room={selectedRoom} saving={saving} onSubmit={event => void saveRoom(event, selectedRoom)} />
            <p><button disabled={saving} onClick={() => setEditing(false)}>Cancel</button></p>
          </> : <>
            <h3>Room {selectedRoom.number}</h3>
            <p>Type: <span className="capitalize">{selectedRoom.type}</span></p>
            <p>Nightly Rate: {money(selectedRoom.rate)}</p>
            <p>Capacity: {selectedRoom.capacity} guests</p>
            <p>Status: {selectedRoom.status === 'available' ? 'In service' : 'Maintenance'}</p>
            <div className="actions">
              <button disabled={loading || saving} onClick={() => setEditing(true)}>Edit Room</button>
              <button disabled={loading || saving} onClick={() => void deleteRoom(selectedRoom)}>Delete Room</button>
            </div>
          </>}
        </section> : !loading && <p>{route.roomId ? 'Room not found.' : 'Choose a room to see its details.'}</p>}
        <p><a href="#roomlist">Back to Roomlist</a></p>
      </>}
    </main>
  </div>
}

function RoomForm({ room, saving, onSubmit }: {
  room?: Room; saving: boolean; onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
  return <form onSubmit={onSubmit}>
    <fieldset disabled={saving}>
      <label>Room number<input name="number" maxLength={20} defaultValue={room?.number} required /></label>
      <label>Room type<select name="type" defaultValue={room?.type || 'standard'}>
        <option value="standard">Standard</option><option value="deluxe">Deluxe</option><option value="suite">Suite</option>
      </select></label>
      <label>Nightly rate<input name="rate" type="number" min="0" max="99999999.99" step="0.01" defaultValue={room?.rate} required /></label>
      <label>Guest capacity<input name="capacity" type="number" min="1" max="20" defaultValue={room?.capacity || 2} required /></label>
      <label>Status<select name="status" defaultValue={room?.status || 'available'}>
        <option value="available">In service</option><option value="maintenance">Maintenance</option>
      </select></label>
      <button type="submit">{saving ? 'Saving...' : room ? 'Save Changes' : 'Add Room'}</button>
    </fieldset>
  </form>
}
