import { useState } from 'react'

// ── Data ──────────────────────────────────────────────────────────────────────

const ITEMS = [
  {
    id: 1,
    img: 'https://images.unsplash.com/photo-1637486069202-b1163268c240?w=480&h=320&fit=crop&auto=format',
    title: 'Brown bifold wallet',
    refined: 'Open bi-fold, tan pebbled leather, multiple card slots visible, possible Coach branding, no cash inside',
    where: 'Washington Square Park fountain area',
    when: '2 hrs ago',
    status: 'active',
    city: 'New York, NY',
  },
  {
    id: 2,
    img: 'https://images.unsplash.com/photo-1604445415362-2a9840bd5ff6?w=480&h=320&fit=crop&auto=format',
    title: 'House key set — 4 keys',
    refined: '4 keys on plain steel ring: 2 Medeco, 1 Yale, 1 small padlock key; no label or tag attached',
    where: 'Kroger checkout lane, Midtown',
    when: '5 hrs ago',
    status: 'active',
    city: 'Atlanta, GA',
  },
  {
    id: 3,
    img: 'https://images.unsplash.com/photo-1567473810954-507d59716c25?w=480&h=320&fit=crop&auto=format',
    title: 'Gold aviator sunglasses',
    refined: 'Ray-Ban style aviators, gold metal frame, green-tinted lenses, spring hinges, no case included',
    where: 'Seat 14C, United Flight UA1847',
    when: '1 day ago',
    status: 'matched',
    city: 'Chicago, IL',
  },
  {
    id: 4,
    img: 'https://images.unsplash.com/photo-1633818807431-14d29e583bcb?w=480&h=320&fit=crop&auto=format',
    title: 'Car key fob + house key',
    refined: 'Toyota smart key fob (black), 1 door key on rubber band, small grocery loyalty card clipped to ring',
    where: 'Planet Fitness locker room bench',
    when: '1 day ago',
    status: 'active',
    city: 'Houston, TX',
  },
  {
    id: 5,
    img: 'https://images.unsplash.com/photo-1587310311582-aa7610e90826?w=480&h=320&fit=crop&auto=format',
    title: 'Black square-frame glasses',
    refined: 'Thick black acetate frame, strong prescription lenses, no brand markings visible, no case',
    where: 'Reading Room, Boston Public Library',
    when: '2 days ago',
    status: 'matched',
    city: 'Boston, MA',
  },
  {
    id: 6,
    img: 'https://images.unsplash.com/photo-1781751594989-ac3ad190db3c?w=480&h=320&fit=crop&auto=format',
    title: 'Black leather backpack',
    refined: 'Medium size, black leather with silver chain accent, 2 compartments, empty laptop sleeve inside',
    where: 'Platform 3B, Union Station',
    when: '2 days ago',
    status: 'active',
    city: 'Los Angeles, CA',
  },
]

const STEPS = [
  {
    n: '01',
    title: 'Finder photographs the item',
    body: 'No lengthy forms. Snap a photo, note the location, and submit. Takes under a minute from anywhere.',
  },
  {
    n: '02',
    title: 'AI extracts a precise profile',
    body: "Our model reads brand, color, material, condition, and distinguishing details — turning a raw photo into a searchable record.",
  },
  {
    n: '03',
    title: 'Owner gets a match alert',
    body: 'When a lost report matches a found item, both parties receive a notification with contact details to arrange pickup.',
  },
]

const STATS = [
  { v: '1,847', label: 'Items reunited', note: 'since launch · March 2024' },
  { v: '143', label: 'Active searches', note: 'open right now' },
  { v: '38', label: 'Found this week', note: 'reported by community' },
  { v: '2.4 days', label: 'Avg. time to reunite', note: 'when both sides report' },
]

const MATCH_ROWS = [
  ['Brown leather wallet', 'Brown leather wallet'],
  ['Coach brand', 'Coach logo stitching visible'],
  ['Lost near NYU campus', 'Found: Washington Sq Park'],
  ['Tuesday afternoon', 'Found ~3:00 PM Tuesday'],
]

// ── Shared styles ─────────────────────────────────────────────────────────────

const serif = { fontFamily: "'Fraunces', serif" }
const sans = { fontFamily: "'Inter', sans-serif" }

// ── Nav ───────────────────────────────────────────────────────────────────────

function Nav() {
  return (
    <nav
      className="sticky top-0 z-50 border-b"
      style={{ backgroundColor: '#FAF8F5', borderColor: '#E7E3DC', ...sans }}
    >
      <div className="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <LogoMark size={28} />
          <span
            className="text-base font-semibold tracking-tight"
            style={{ ...serif, color: '#1C1917' }}
          >
            Reunite
          </span>
        </div>

        <div className="hidden md:flex items-center gap-8 text-sm" style={{ color: '#78716C' }}>
          <a href="#how" className="hover:text-stone-800 transition-colors">How it works</a>
          <a href="#board" className="hover:text-stone-800 transition-colors">Community board</a>
          <a href="#" className="hover:text-stone-800 transition-colors">FAQ</a>
        </div>

        <div className="flex items-center gap-2">
          <button
            className="text-sm px-4 py-2 rounded-full hover:bg-stone-100 transition-colors"
            style={{ color: '#1C1917' }}
          >
            Sign in
          </button>
          <button
            className="text-sm px-5 py-2 rounded-full font-medium transition-all hover:opacity-90 active:scale-95"
            style={{ backgroundColor: '#1C1917', color: '#FAF8F5' }}
          >
            Report an item
          </button>
        </div>
      </div>
    </nav>
  )
}

function LogoMark({ size = 28 }: { size?: number }) {
  return (
    <svg width={size} height={size} viewBox="0 0 28 28" fill="none" aria-hidden>
      <circle cx="14" cy="14" r="14" fill="#C4622D" />
      <path
        d="M14 7c-3.866 0-7 3.134-7 7 0 2.21 1.03 4.183 2.645 5.474L8 21h12l-1.645-1.526C19.97 18.183 21 16.21 21 14c0-3.866-3.134-7-7-7z"
        fill="white"
        fillOpacity="0.25"
      />
      <circle cx="14" cy="14" r="3" fill="white" />
      <path d="M14 8v3M14 17v3M8 14H5M23 14h-3" stroke="white" strokeWidth="1.5" strokeLinecap="round" strokeOpacity="0.5" />
    </svg>
  )
}

// ── Hero ──────────────────────────────────────────────────────────────────────

function Hero() {
  return (
    <section className="max-w-6xl mx-auto px-6 pt-14 pb-20">
      <div className="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-12 items-start">
        {/* Left — headline + CTAs */}
        <div>
          <div
            className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium mb-8"
            style={{ backgroundColor: '#F2E8E1', color: '#C4622D', ...sans }}
          >
            <span className="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse inline-block" />
            12 active matches in progress right now
          </div>

          <h1
            className="text-5xl xl:text-[3.75rem] font-medium leading-[1.06] mb-6"
            style={{ ...serif, color: '#1C1917', letterSpacing: '-0.025em' }}
          >
            Things get lost.<br />
            <em className="not-italic" style={{ color: '#C4622D' }}>Communities</em>
            <br />
            bring them back.
          </h1>

          <p
            className="text-lg leading-relaxed mb-10"
            style={{ color: '#78716C', maxWidth: '46ch', ...sans }}
          >
            Upload a photo of what you found — or report what you lost. Our system
            uses AI to extract item details, then notifies you the moment a match is found.
          </p>

          <div className="flex flex-col sm:flex-row gap-3">
            <button
              className="flex flex-col gap-0.5 px-7 py-4 rounded-2xl text-left transition-all hover:opacity-95 hover:-translate-y-0.5 active:scale-[0.98]"
              style={{ backgroundColor: '#C4622D', color: '#FAF8F5', ...sans }}
            >
              <span className="text-[10px] uppercase tracking-widest opacity-70 font-medium">
                I found something
              </span>
              <span className="font-semibold text-sm">Upload a found item &rarr;</span>
            </button>
            <button
              className="flex flex-col gap-0.5 px-7 py-4 rounded-2xl text-left border-2 transition-all hover:-translate-y-0.5 active:scale-[0.98] bg-white"
              style={{ borderColor: '#E7E3DC', ...sans }}
            >
              <span className="text-[10px] uppercase tracking-widest font-medium" style={{ color: '#A8A29E' }}>
                I lost something
              </span>
              <span className="font-semibold text-sm" style={{ color: '#1C1917' }}>
                Report a missing item &rarr;
              </span>
            </button>
          </div>
        </div>

        {/* Right — live activity feed */}
        <div
          className="rounded-2xl border overflow-hidden shadow-sm"
          style={{ backgroundColor: '#fff', borderColor: '#E7E3DC' }}
        >
          <div
            className="px-4 py-3 border-b flex items-center justify-between"
            style={{ borderColor: '#E7E3DC', backgroundColor: '#FAFAF9' }}
          >
            <div className="flex items-center gap-2">
              <span className="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse inline-block" />
              <span className="text-xs font-semibold" style={{ color: '#78716C', ...sans }}>
                Live activity
              </span>
            </div>
            <span className="text-[10px]" style={{ color: '#A8A29E', ...sans }}>
              Updated just now
            </span>
          </div>

          {ITEMS.slice(0, 5).map((item, i) => (
            <div
              key={item.id}
              className="px-4 py-3 flex items-start gap-3 hover:bg-stone-50 transition-colors cursor-pointer"
              style={{
                borderBottom: i < 4 ? '1px solid #F0EDE8' : 'none',
              }}
            >
              <img
                src={item.img}
                alt={item.title}
                className="w-10 h-10 rounded-lg object-cover flex-shrink-0 bg-stone-100"
              />
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-0.5">
                  <span
                    className="text-xs font-semibold truncate"
                    style={{ color: '#1C1917', ...sans }}
                  >
                    {item.title}
                  </span>
                  {item.status === 'matched' && (
                    <span
                      className="text-[9px] px-1.5 py-0.5 rounded-full font-bold flex-shrink-0 uppercase tracking-wide"
                      style={{ backgroundColor: '#D1FAE5', color: '#065F46' }}
                    >
                      Reunited
                    </span>
                  )}
                </div>
                <div className="text-[11px]" style={{ color: '#A8A29E', ...sans }}>
                  {item.city} &middot; {item.when}
                </div>
              </div>
            </div>
          ))}

          <div className="px-4 py-3 text-center border-t" style={{ borderColor: '#F0EDE8' }}>
            <a
              href="#board"
              className="text-xs font-semibold"
              style={{ color: '#C4622D', ...sans }}
            >
              See all 143 open searches &rarr;
            </a>
          </div>
        </div>
      </div>
    </section>
  )
}

// ── Stats Bar ─────────────────────────────────────────────────────────────────

function StatsBar() {
  return (
    <section className="max-w-6xl mx-auto px-6 mb-12">
      <div 
        className="rounded-3xl p-8 border shadow-sm"
        style={{ backgroundColor: '#FFFFFF', borderColor: '#E7E3DC' }}
      >
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-8">
          {STATS.map((s, i) => (
            <div key={s.label} className={`${i > 0 ? 'lg:border-l' : ''} lg:pl-8 flex flex-col justify-center`} style={{ borderColor: '#F0EDE8' }}>
              <div
                className="text-4xl font-semibold mb-1 tabular-nums"
                style={{ ...serif, color: '#C4622D' }}
              >
                {s.v}
              </div>
              <div className="text-sm font-semibold mb-0.5" style={{ color: '#1C1917', ...sans }}>
                {s.label}
              </div>
              <div className="text-xs" style={{ color: '#78716C', ...sans }}>
                {s.note}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

// ── How It Works ──────────────────────────────────────────────────────────────

function HowItWorks() {
  return (
    <section id="how" className="py-20" style={{ backgroundColor: '#FAF8F5' }}>
      <div className="max-w-6xl mx-auto px-6">
        <div className="mb-14">
          <p
            className="text-xs uppercase tracking-widest font-semibold mb-3"
            style={{ color: '#C4622D', ...sans }}
          >
            How it works
          </p>
          <h2
            className="text-4xl font-medium leading-tight"
            style={{ ...serif, color: '#1C1917', letterSpacing: '-0.022em' }}
          >
            From found to returned<br />in three steps
          </h2>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-10 mb-16">
          {STEPS.map((step) => (
            <div key={step.n}>
              <div
                className="text-6xl font-medium mb-5 select-none leading-none"
                style={{ ...serif, color: '#EDE9E4' }}
              >
                {step.n}
              </div>
              <h3 className="text-base font-semibold mb-2" style={{ color: '#1C1917', ...sans }}>
                {step.title}
              </h3>
              <p className="text-sm leading-relaxed" style={{ color: '#78716C', ...sans }}>
                {step.body}
              </p>
            </div>
          ))}
        </div>

        {/* AI extraction demo */}
        <div
          className="rounded-2xl border overflow-hidden"
          style={{ borderColor: '#E7E3DC', backgroundColor: '#fff' }}
        >
          <div
            className="px-6 py-3 border-b flex items-center gap-2"
            style={{ borderColor: '#E7E3DC', backgroundColor: '#FAFAF9' }}
          >
            <div
              className="w-1.5 h-1.5 rounded-full"
              style={{ backgroundColor: '#C4622D' }}
            />
            <span className="text-xs font-semibold" style={{ color: '#78716C', ...sans }}>
              AI extraction &mdash; live example
            </span>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2">
            {/* Before: what the finder uploads */}
            <div className="p-8 md:border-r" style={{ borderColor: '#E7E3DC' }}>
              <p
                className="text-[10px] uppercase tracking-widest font-bold mb-4"
                style={{ color: '#A8A29E', ...sans }}
              >
                What the finder uploads
              </p>
              <div className="rounded-xl overflow-hidden mb-4 bg-stone-100">
                <img
                  src="https://images.unsplash.com/photo-1637486069202-b1163268c240?w=580&h=300&fit=crop&auto=format"
                  alt="Open brown leather wallet found on a wooden table"
                  className="w-full h-44 object-cover"
                />
              </div>
              <div
                className="rounded-xl p-4 text-sm italic leading-relaxed"
                style={{ backgroundColor: '#F5F2EE', color: '#78716C', ...sans }}
              >
                &ldquo;Found this wallet near the Washington Square Park fountain around 3 PM Tuesday. Looks like it has cards inside.&rdquo;
              </div>
            </div>

            {/* After: AI-refined profile */}
            <div className="p-8">
              <div className="flex items-center gap-2 mb-5">
                <div
                  className="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0"
                  style={{ backgroundColor: '#C4622D' }}
                >
                  <svg width="9" height="9" viewBox="0 0 9 9" fill="none" aria-hidden>
                    <path
                      d="M1.5 4.5L3.5 6.5L7.5 2.5"
                      stroke="white"
                      strokeWidth="1.5"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                  </svg>
                </div>
                <p
                  className="text-[10px] uppercase tracking-widest font-bold"
                  style={{ color: '#C4622D', ...sans }}
                >
                  AI-refined profile
                </p>
              </div>

              <div className="space-y-3">
                {[
                  ['Category', 'Wallet — bifold'],
                  ['Likely brand', 'Coach (logo stitching visible)'],
                  ['Color', 'Tan / cognac brown'],
                  ['Material', 'Pebbled leather'],
                  ['Condition', 'Light wear; scratch on front-left'],
                  ['Contents', 'Cards visible, no cash'],
                  ['Found at', 'Washington Square Park, NYC'],
                  ['Found on', 'Tuesday ~3:00 PM'],
                ].map(([k, v]) => (
                  <div key={k} className="flex gap-4 items-baseline">
                    <span
                      className="text-[11px] font-semibold w-24 flex-shrink-0"
                      style={{ color: '#A8A29E', ...sans }}
                    >
                      {k}
                    </span>
                    <span className="text-xs" style={{ color: '#1C1917', ...sans }}>
                      {v}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

// ── Community Board ───────────────────────────────────────────────────────────

type Filter = 'all' | 'active' | 'matched'

function CommunityBoard() {
  const [filter, setFilter] = useState<Filter>('all')
  const filtered = filter === 'all' ? ITEMS : ITEMS.filter((i) => i.status === filter)

  return (
    <section id="board" className="py-20" style={{ backgroundColor: '#F5F2EE' }}>
      <div className="max-w-6xl mx-auto px-6">
        <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
          <div>
            <p
              className="text-xs uppercase tracking-widest font-semibold mb-2"
              style={{ color: '#C4622D', ...sans }}
            >
              Community board
            </p>
            <h2
              className="text-4xl font-medium"
              style={{ ...serif, color: '#1C1917', letterSpacing: '-0.022em' }}
            >
              Recently found items
            </h2>
          </div>

          {/* Filter pills */}
          <div
            className="flex items-center gap-1 p-1 rounded-full self-start sm:self-auto"
            style={{ backgroundColor: '#E7E3DC' }}
          >
            {(['all', 'active', 'matched'] as Filter[]).map((f) => (
              <button
                key={f}
                onClick={() => setFilter(f)}
                className="px-4 py-1.5 rounded-full text-xs font-semibold transition-all"
                style={{
                  backgroundColor: filter === f ? '#fff' : 'transparent',
                  color: filter === f ? '#1C1917' : '#78716C',
                  boxShadow: filter === f ? '0 1px 2px rgba(0,0,0,0.08)' : 'none',
                  ...sans,
                }}
              >
                {f === 'all' ? 'All items' : f === 'active' ? 'Searching' : 'Reunited'}
              </button>
            ))}
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {filtered.map((item) => (
            <article
              key={item.id}
              className="rounded-2xl overflow-hidden border hover:shadow-lg transition-all duration-200 cursor-pointer group"
              style={{ backgroundColor: '#fff', borderColor: '#E7E3DC' }}
            >
              <div className="relative overflow-hidden">
                <img
                  src={item.img}
                  alt={item.title}
                  className="w-full h-44 object-cover group-hover:scale-[1.03] transition-transform duration-300 bg-stone-100"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent" />
                <div className="absolute top-3 right-3">
                  {item.status === 'matched' ? (
                    <span
                      className="text-[10px] px-2.5 py-1 rounded-full font-bold uppercase tracking-wide"
                      style={{ backgroundColor: '#D1FAE5', color: '#065F46' }}
                    >
                      &#10003; Reunited
                    </span>
                  ) : (
                    <span
                      className="text-[10px] px-2.5 py-1 rounded-full font-bold uppercase tracking-wide"
                      style={{ backgroundColor: '#FEF3C7', color: '#92400E' }}
                    >
                      Searching
                    </span>
                  )}
                </div>
              </div>

              <div className="p-4">
                <h3 className="font-semibold text-sm mb-1.5" style={{ color: '#1C1917', ...sans }}>
                  {item.title}
                </h3>
                <p
                  className="text-xs leading-relaxed mb-3"
                  style={{ color: '#78716C', ...sans, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}
                >
                  {item.refined}
                </p>
                <div
                  className="flex items-center justify-between pt-3 border-t"
                  style={{ borderColor: '#F0EDE8' }}
                >
                  <span className="text-[11px] flex items-center gap-1" style={{ color: '#A8A29E', ...sans }}>
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden>
                      <path d="M5 1C3.343 1 2 2.343 2 4c0 2.5 3 5 3 5s3-2.5 3-5c0-1.657-1.343-3-3-3z" fill="#A8A29E" />
                      <circle cx="5" cy="4" r="1" fill="white" />
                    </svg>
                    {item.city}
                  </span>
                  <span className="text-[11px]" style={{ color: '#A8A29E', ...sans }}>
                    {item.when}
                  </span>
                </div>
              </div>
            </article>
          ))}
        </div>

        <div className="text-center mt-10">
          <button
            className="px-6 py-3 rounded-full border text-sm font-semibold hover:bg-white transition-colors"
            style={{ borderColor: '#D6D3D1', color: '#1C1917', ...sans }}
          >
            Browse all found items &rarr;
          </button>
        </div>
      </div>
    </section>
  )
}

// ── Notification Preview ──────────────────────────────────────────────────────

function NotifySection() {
  return (
    <section className="py-20" style={{ backgroundColor: '#FAF8F5' }}>
      <div className="max-w-6xl mx-auto px-6">
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-14 items-center">
          {/* Left copy */}
          <div>
            <p
              className="text-xs uppercase tracking-widest font-semibold mb-4"
              style={{ color: '#C4622D', ...sans }}
            >
              Instant notifications
            </p>
            <h2
              className="text-4xl font-medium leading-tight mb-5"
              style={{ ...serif, color: '#1C1917', letterSpacing: '-0.022em' }}
            >
              You hear about a match<br />before you even check.
            </h2>
            <p className="text-base leading-relaxed mb-7" style={{ color: '#78716C', maxWidth: '46ch', ...sans }}>
              The moment our system finds a high-confidence match, both the finder and the owner
              receive a notification with contact details to arrange pickup.
            </p>
            <ul className="space-y-3.5">
              {[
                'Match confidence shown before contact details are shared',
                'Location and time context included in every alert',
                'No personal data exchanged until both parties confirm',
              ].map((line) => (
                <li key={line} className="flex items-start gap-3 text-sm" style={{ color: '#57534E', ...sans }}>
                  <span
                    className="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                    style={{ backgroundColor: '#F2E8E1' }}
                  >
                    <svg width="9" height="9" viewBox="0 0 9 9" fill="none" aria-hidden>
                      <path
                        d="M1.5 4.5L3.5 6.5L7.5 2.5"
                        stroke="#C4622D"
                        strokeWidth="1.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      />
                    </svg>
                  </span>
                  {line}
                </li>
              ))}
            </ul>
          </div>

          {/* Right — notification mockups */}
          <div className="flex flex-col gap-4">
            {/* Push notification card */}
            <div
              className="rounded-2xl p-5 border shadow-sm"
              style={{ backgroundColor: '#fff', borderColor: '#E7E3DC' }}
            >
              <div className="flex items-start gap-4">
                <div
                  className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                  style={{ backgroundColor: '#C4622D' }}
                >
                  <LogoMark size={20} />
                </div>
                <div className="flex-1">
                  <div className="flex items-center justify-between mb-1.5">
                    <span className="text-xs font-bold" style={{ color: '#1C1917', ...sans }}>
                      Reunite &middot; Match found
                    </span>
                    <span className="text-[10px]" style={{ color: '#A8A29E', ...sans }}>just now</span>
                  </div>
                  <p className="text-xs leading-relaxed" style={{ color: '#57534E', ...sans }}>
                    Great news! We found a{' '}
                    <strong style={{ color: '#1C1917' }}>94% match</strong> for your lost{' '}
                    <strong style={{ color: '#1C1917' }}>brown Coach wallet</strong>. It was found
                    at Washington Square Park on Tuesday. Tap to connect with the finder.
                  </p>
                </div>
              </div>
            </div>

            {/* Match comparison card */}
            <div
              className="rounded-2xl border overflow-hidden shadow-sm"
              style={{ borderColor: '#E7E3DC', backgroundColor: '#fff' }}
            >
              <div
                className="px-5 py-3 border-b flex items-center gap-3"
                style={{ borderColor: '#E7E3DC', backgroundColor: '#FAFAF9' }}
              >
                <span
                  className="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full"
                  style={{ backgroundColor: '#D1FAE5', color: '#065F46' }}
                >
                  94% match
                </span>
                <span className="text-xs" style={{ color: '#78716C', ...sans }}>
                  8 overlapping attributes confirmed
                </span>
              </div>

              <div className="px-5 py-4">
                <div className="grid grid-cols-2 gap-x-4 mb-2 pb-2 border-b" style={{ borderColor: '#F0EDE8' }}>
                  <span className="text-[10px] font-bold uppercase tracking-widest" style={{ color: '#A8A29E', ...sans }}>
                    Your report
                  </span>
                  <span className="text-[10px] font-bold uppercase tracking-widest" style={{ color: '#A8A29E', ...sans }}>
                    What was found
                  </span>
                </div>
                {MATCH_ROWS.map(([a, b]) => (
                  <div
                    key={a}
                    className="grid grid-cols-2 gap-x-4 py-2 border-b last:border-0"
                    style={{ borderColor: '#F0EDE8' }}
                  >
                    <span className="text-xs" style={{ color: '#78716C', ...sans }}>{a}</span>
                    <span className="text-xs flex items-center gap-1.5" style={{ color: '#059669', ...sans }}>
                      <svg width="9" height="9" viewBox="0 0 9 9" fill="none" aria-hidden>
                        <path
                          d="M1.5 4.5L3.5 6.5L7.5 2.5"
                          stroke="#059669"
                          strokeWidth="1.5"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                        />
                      </svg>
                      {b}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

// ── CTA Banner ────────────────────────────────────────────────────────────────

function CTABanner() {
  return (
    <section className="py-16" style={{ backgroundColor: '#F5F2EE' }}>
      <div className="max-w-6xl mx-auto px-6">
        <div
          className="rounded-3xl px-10 py-14 flex flex-col md:flex-row items-start md:items-center justify-between gap-8"
          style={{ backgroundColor: '#1C1917' }}
        >
          <div>
            <h2
              className="text-3xl font-medium mb-2"
              style={{ ...serif, color: '#FAF8F5', letterSpacing: '-0.02em' }}
            >
              Lost something recently?
            </h2>
            <p className="text-sm" style={{ color: '#78716C', ...sans }}>
              File a report in under 2 minutes. We&apos;ll search our active found items and alert you immediately if something matches.
            </p>
          </div>
          <div className="flex flex-col sm:flex-row gap-3 flex-shrink-0">
            <button
              className="px-6 py-3 rounded-xl text-sm font-semibold transition-all hover:opacity-90 active:scale-95"
              style={{ backgroundColor: '#C4622D', color: '#FAF8F5', ...sans }}
            >
              Report a lost item
            </button>
            <button
              className="px-6 py-3 rounded-xl text-sm font-semibold transition-all hover:bg-stone-800"
              style={{ backgroundColor: '#292524', color: '#FAF8F5', ...sans }}
            >
              Browse found items
            </button>
          </div>
        </div>
      </div>
    </section>
  )
}

// ── Footer ────────────────────────────────────────────────────────────────────

function Footer() {
  return (
    <footer className="border-t py-10" style={{ backgroundColor: '#FAF8F5', borderColor: '#E7E3DC' }}>
      <div className="max-w-6xl mx-auto px-6">
        <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
          <div>
            <div className="flex items-center gap-2 mb-2">
              <LogoMark size={22} />
              <span
                className="font-semibold text-sm"
                style={{ ...serif, color: '#1C1917' }}
              >
                Reunite
              </span>
            </div>
            <p className="text-xs" style={{ color: '#A8A29E', maxWidth: '40ch', ...sans }}>
              A community-powered lost &amp; found platform. Not affiliated with any city, transit system, or municipality.
            </p>
          </div>

          <div className="flex flex-wrap gap-x-7 gap-y-2 text-xs" style={{ color: '#78716C', ...sans }}>
            <a href="#" className="hover:text-stone-800 transition-colors">Privacy policy</a>
            <a href="#" className="hover:text-stone-800 transition-colors">Terms of use</a>
            <a href="#" className="hover:text-stone-800 transition-colors">Contact us</a>
            <a href="#" className="hover:text-stone-800 transition-colors">GitHub</a>
            <span style={{ color: '#D6D3D1' }}>&copy; 2024 Reunite</span>
          </div>
        </div>
      </div>
    </footer>
  )
}

// ── App ───────────────────────────────────────────────────────────────────────

export default function App() {
  return (
    <div style={{ backgroundColor: '#FAF8F5', ...sans }}>
      <Nav />
      <Hero />
      <StatsBar />
      <HowItWorks />
      <CommunityBoard />
      <NotifySection />
      <CTABanner />
      <Footer />
    </div>
  )
}
