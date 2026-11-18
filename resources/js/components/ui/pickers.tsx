import {
  Children, Fragment, isValidElement, useCallback, useEffect, useId, useLayoutEffect, useMemo, useRef, useState,
  type ButtonHTMLAttributes, type KeyboardEvent, type ReactElement, type ReactNode, type RefObject,
} from 'react'
import { createPortal } from 'react-dom'
import { Calendar, Check, ChevronDown, ChevronLeft, ChevronRight, ChevronsUpDown, Search, X } from 'lucide-react'
import { cn } from '@/lib/utils'

/* ====================================================================== shared: popover */

interface Position { top: number; left: number; width?: number }

/** Places a floating panel under (or, when there is no room, above) its trigger and keeps it there while the page moves. */
function useAnchoredPanel(open: boolean, triggerRef: RefObject<HTMLElement | null>, panelRef: RefObject<HTMLElement | null>, matchWidth: boolean) {
  const [position, setPosition] = useState<Position | null>(null)

  const update = useCallback(() => {
    const trigger = triggerRef.current
    if (!trigger) return
    const box = trigger.getBoundingClientRect()
    const panelHeight = panelRef.current?.offsetHeight ?? 320
    const panelWidth = panelRef.current?.offsetWidth ?? box.width
    const gap = 6
    const margin = 8
    const below = window.innerHeight - box.bottom - margin
    const above = box.top - margin
    const flip = below < panelHeight && above > below
    const top = flip ? Math.max(margin, box.top - gap - panelHeight) : box.bottom + gap
    const left = Math.max(margin, Math.min(box.left, window.innerWidth - panelWidth - margin))
    setPosition({ top, left, width: matchWidth ? box.width : undefined })
  }, [matchWidth, panelRef, triggerRef])

  useLayoutEffect(() => {
    if (!open) {
      setPosition(null)
      return
    }
    update()
    const observer = typeof ResizeObserver !== 'undefined' && panelRef.current ? new ResizeObserver(update) : null
    if (observer && panelRef.current) observer.observe(panelRef.current)
    window.addEventListener('resize', update)
    window.addEventListener('scroll', update, true)
    return () => {
      observer?.disconnect()
      window.removeEventListener('resize', update)
      window.removeEventListener('scroll', update, true)
    }
  }, [open, update, panelRef])

  return position
}

/** Closes a panel on an outside click or Escape. Escape is handled first, so it never closes a modal underneath. */
function useDismiss(open: boolean, close: () => void, triggerRef: RefObject<HTMLElement | null>, panelRef: RefObject<HTMLElement | null>) {
  useEffect(() => {
    if (!open) return
    const onDown = (e: MouseEvent) => {
      const target = e.target as Node
      if (!triggerRef.current?.contains(target) && !panelRef.current?.contains(target)) close()
    }
    const onKey = (e: globalThis.KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation()
        close()
        triggerRef.current?.focus()
      }
    }
    document.addEventListener('mousedown', onDown)
    window.addEventListener('keydown', onKey, true)
    return () => {
      document.removeEventListener('mousedown', onDown)
      window.removeEventListener('keydown', onKey, true)
    }
  }, [open, close, triggerRef, panelRef])
}

const TRIGGER = 'input flex items-center justify-between gap-2 text-left'
const TRIGGER_OPEN = 'border-brand-500 ring-4 ring-brand-100'

/* ====================================================================== searchable select */

interface OptionItem { value: string; label: string; disabled: boolean }

const textOf = (node: ReactNode): string => {
  if (node === null || node === undefined || typeof node === 'boolean') return ''
  if (typeof node === 'string' || typeof node === 'number') return String(node)
  if (Array.isArray(node)) return node.map(textOf).join('')
  if (isValidElement<{ children?: ReactNode }>(node)) return textOf(node.props.children)
  return ''
}

/** Reads the `<option>` children, so every existing `<Select>` keeps working as written. */
function readOptions(children: ReactNode): OptionItem[] {
  const found: OptionItem[] = []
  const walk = (nodes: ReactNode) => {
    Children.forEach(nodes, (child) => {
      if (!isValidElement(child)) return
      const element = child as ReactElement<{ value?: string | number; disabled?: boolean; children?: ReactNode }>
      if (element.type === Fragment) walk(element.props.children)
      else if (element.type === 'option') {
        const label = textOf(element.props.children)
        found.push({ value: String(element.props.value ?? label), label, disabled: !!element.props.disabled })
      }
    })
  }
  walk(children)
  return found
}

export interface SelectChangeEvent { target: { value: string; id?: string; name?: string } }

export interface SelectProps extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'onChange' | 'value' | 'children'> {
  value?: string | number | null
  onChange?: (event: SelectChangeEvent) => void
  children?: ReactNode
  placeholder?: string
  name?: string
  invalid?: boolean
  /** Force the search box on or off. By default it appears once a list has 6 or more options. */
  searchable?: boolean
}

/** A dropdown with search, keyboard control and a check mark on the chosen option. Drop-in for the native select. */
export function Select({ value, onChange, children, className, placeholder, id, name, invalid, searchable, disabled, ...rest }: SelectProps) {
  const options = useMemo(() => readOptions(children), [children])
  const current = value === null || value === undefined ? '' : String(value)
  const selected = options.find((o) => o.value === current)
  const showSearch = searchable ?? options.length >= 6

  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [highlight, setHighlight] = useState(0)
  const triggerRef = useRef<HTMLButtonElement>(null)
  const panelRef = useRef<HTMLDivElement>(null)
  const searchRef = useRef<HTMLInputElement>(null)
  const listRef = useRef<HTMLUListElement>(null)
  const listId = useId()

  const close = useCallback(() => setOpen(false), [])
  useDismiss(open, close, triggerRef, panelRef)
  const position = useAnchoredPanel(open, triggerRef, panelRef, true)

  const filtered = useMemo(() => {
    const text = query.trim().toLowerCase()
    return text === '' ? options : options.filter((o) => o.label.toLowerCase().includes(text))
  }, [options, query])

  // On open: forget the old search, start on the chosen option and put the cursor where typing or arrows work.
  useEffect(() => {
    if (!open) {
      setQuery('')
      return
    }
    setHighlight(Math.max(0, options.findIndex((o) => o.value === current)))
    const focus = window.setTimeout(() => (showSearch ? searchRef.current : panelRef.current)?.focus({ preventScroll: true }), 0)
    return () => window.clearTimeout(focus)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  useEffect(() => setHighlight(0), [query])

  useEffect(() => {
    if (open) listRef.current?.querySelector<HTMLElement>('[data-active="true"]')?.scrollIntoView({ block: 'nearest' })
  }, [open, highlight, filtered])

  const choose = (item: OptionItem) => {
    if (item.disabled) return
    onChange?.({ target: { value: item.value, id, name } })
    setOpen(false)
    triggerRef.current?.focus()
  }

  const step = (direction: 1 | -1) => {
    if (filtered.length === 0) return
    let next = highlight
    for (let i = 0; i < filtered.length; i++) {
      next = (next + direction + filtered.length) % filtered.length
      if (!filtered[next].disabled) break
    }
    setHighlight(next)
  }

  const onPanelKey = (e: KeyboardEvent<HTMLDivElement>) => {
    if (e.key === 'ArrowDown') { e.preventDefault(); step(1) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); step(-1) }
    else if (e.key === 'Home') { e.preventDefault(); setHighlight(0) }
    else if (e.key === 'End') { e.preventDefault(); setHighlight(Math.max(0, filtered.length - 1)) }
    else if (e.key === 'Enter') { e.preventDefault(); if (filtered[highlight]) choose(filtered[highlight]) }
    else if (e.key === 'Tab') setOpen(false)
  }

  const label = selected?.label ?? placeholder ?? 'Select…'
  const isPlaceholder = !selected || (selected.value === '' && selected.label !== '')

  return (
    <>
      <button
        ref={triggerRef}
        type="button"
        id={id}
        role="combobox"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={open ? listId : undefined}
        aria-invalid={invalid || undefined}
        disabled={disabled}
        onClick={() => setOpen((v) => !v)}
        onKeyDown={(e) => {
          if (!open && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            e.preventDefault()
            setOpen(true)
          }
        }}
        className={cn(TRIGGER, 'cursor-pointer', open && TRIGGER_OPEN, invalid && 'border-rose-400', className)}
        {...rest}
      >
        <span className={cn('min-w-0 truncate', isPlaceholder && 'text-ink-500')}>{label}</span>
        <ChevronsUpDown className="size-4 shrink-0 text-ink-400" aria-hidden />
      </button>

      {open && createPortal(
        <div
          ref={panelRef}
          tabIndex={-1}
          onKeyDown={onPanelKey}
          onMouseDown={(e) => e.stopPropagation()}
          onClick={(e) => e.stopPropagation()}
          className="menu-pop fixed z-[80] overflow-hidden rounded-xl border border-line bg-surface shadow-pop outline-none"
          style={position ? { top: position.top, left: position.left, width: Math.max(position.width ?? 0, 200) } : { top: 0, left: 0, visibility: 'hidden' }}
        >
          {showSearch && (
            <div className="border-b border-line p-2">
              <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400" aria-hidden />
                <input
                  ref={searchRef}
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder="Search…"
                  aria-label="Search options"
                  aria-controls={listId}
                  autoComplete="off"
                  className="h-10 w-full rounded-lg border border-brand-500 bg-surface pr-8 pl-9 text-sm text-ink-900 ring-2 ring-brand-100 outline-none placeholder:text-ink-400"
                />
                {query && (
                  <button type="button" onClick={() => { setQuery(''); searchRef.current?.focus() }} className="absolute top-1/2 right-2 -translate-y-1/2 rounded p-0.5 text-ink-400 hover:text-ink-700" aria-label="Clear search">
                    <X className="size-3.5" />
                  </button>
                )}
              </div>
            </div>
          )}
          <ul ref={listRef} id={listId} role="listbox" className="scrollbar-thin max-h-64 overflow-y-auto p-1">
            {filtered.length === 0 ? (
              <li className="px-3 py-6 text-center text-[13px] text-ink-500">No results{query ? ` for “${query}”` : ''}</li>
            ) : (
              filtered.map((item, i) => {
                const isSelected = item.value === current
                return (
                  <li key={item.value + i} role="option" aria-selected={isSelected} aria-disabled={item.disabled || undefined} data-active={i === highlight}>
                    <button
                      type="button"
                      tabIndex={-1}
                      disabled={item.disabled}
                      onClick={() => choose(item)}
                      onMouseMove={() => i !== highlight && setHighlight(i)}
                      className={cn(
                        'flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-left text-[13.5px] transition-colors disabled:cursor-not-allowed disabled:opacity-45',
                        i === highlight ? 'bg-brand-50 text-ink-900' : 'text-ink-700',
                        isSelected && 'font-semibold text-brand-700',
                      )}
                    >
                      <span className="flex size-4 shrink-0 items-center justify-center">{isSelected && <Check className="size-4 text-brand-600" strokeWidth={2.5} />}</span>
                      <span className="min-w-0 truncate">{item.label}</span>
                    </button>
                  </li>
                )
              })
            )}
          </ul>
        </div>,
        document.body,
      )}
    </>
  )
}

/* ====================================================================== date picker */

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

const pad = (n: number) => String(n).padStart(2, '0')
const toIso = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
const parseIso = (value?: string | null): Date | null => {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value ?? '')
  if (!m) return null
  const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]))
  return Number.isNaN(d.getTime()) ? null : d
}
const addDays = (d: Date, n: number) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n)
const addMonths = (d: Date, n: number) => {
  const target = new Date(d.getFullYear(), d.getMonth() + n, 1)
  const last = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate()
  return new Date(target.getFullYear(), target.getMonth(), Math.min(d.getDate(), last))
}
const shown = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })

export interface DateChangeEvent { target: { value: string; id?: string; name?: string } }

export interface DatePickerProps extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'onChange' | 'value' | 'children' | 'min' | 'max'> {
  /** YYYY-MM-DD, or an empty string for no date. */
  value?: string | null
  onChange?: (event: DateChangeEvent) => void
  min?: string
  max?: string
  placeholder?: string
  name?: string
  invalid?: boolean
  /** Show the Clear button. On by default. */
  clearable?: boolean
}

/** A calendar dropdown. Same value and change handler as `<input type="date">`: a YYYY-MM-DD string. */
export function DatePicker({ value, onChange, min, max, placeholder = 'Select date', name, invalid, clearable = true, id, className, disabled, ...rest }: DatePickerProps) {
  const selected = parseIso(value)
  const minDate = parseIso(min)
  const maxDate = parseIso(max)

  const [open, setOpen] = useState(false)
  const [view, setView] = useState<'days' | 'months' | 'years'>('days')
  const [month, setMonth] = useState(() => selected ?? new Date())
  const [focused, setFocused] = useState(() => selected ?? new Date())
  const [decade, setDecade] = useState(() => Math.floor((selected ?? new Date()).getFullYear() / 12) * 12)
  const [keyboard, setKeyboard] = useState(false)
  const triggerRef = useRef<HTMLButtonElement>(null)
  const panelRef = useRef<HTMLDivElement>(null)
  const gridRef = useRef<HTMLDivElement>(null)

  const close = useCallback(() => setOpen(false), [])
  useDismiss(open, close, triggerRef, panelRef)
  const position = useAnchoredPanel(open, triggerRef, panelRef, false)

  useEffect(() => {
    if (!open) return
    const start = parseIso(value) ?? new Date()
    setMonth(start)
    setFocused(start)
    setView('days')
    setKeyboard(false)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  // Arrow-key navigation moves real focus between day buttons.
  useEffect(() => {
    if (open && view === 'days' && keyboard) gridRef.current?.querySelector<HTMLElement>(`[data-iso="${toIso(focused)}"]`)?.focus()
  }, [open, view, keyboard, focused])

  const blocked = (d: Date) => (minDate !== null && d < minDate) || (maxDate !== null && d > maxDate)
  const emit = (next: string) => onChange?.({ target: { value: next, id, name } })

  const pick = (d: Date) => {
    if (blocked(d)) return
    emit(toIso(d))
    setOpen(false)
    triggerRef.current?.focus()
  }

  const cells = useMemo(() => {
    const first = new Date(month.getFullYear(), month.getMonth(), 1)
    const start = addDays(first, -first.getDay())
    return Array.from({ length: 42 }, (_, i) => addDays(start, i))
  }, [month])

  const today = new Date()
  const todayIso = toIso(today)
  const selectedIso = selected ? toIso(selected) : ''

  const onGridKey = (e: KeyboardEvent<HTMLDivElement>) => {
    const moves: Record<string, (d: Date) => Date> = {
      ArrowLeft: (d) => addDays(d, -1), ArrowRight: (d) => addDays(d, 1), ArrowUp: (d) => addDays(d, -7), ArrowDown: (d) => addDays(d, 7),
      PageUp: (d) => addMonths(d, -1), PageDown: (d) => addMonths(d, 1),
      Home: (d) => addDays(d, -d.getDay()), End: (d) => addDays(d, 6 - d.getDay()),
    }
    const move = moves[e.key]
    if (!move) return
    e.preventDefault()
    const next = move(focused)
    setKeyboard(true)
    setFocused(next)
    setMonth(next)
  }

  const shiftMonth = (n: number) => {
    const next = addMonths(month, n)
    setMonth(next)
    setFocused(next)
  }

  const prevDisabled = view === 'days' && minDate !== null && new Date(month.getFullYear(), month.getMonth(), 0) < minDate
  const nextDisabled = view === 'days' && maxDate !== null && new Date(month.getFullYear(), month.getMonth() + 1, 1) > maxDate

  const nav = 'flex size-8 items-center justify-center rounded-full text-ink-700 transition hover:bg-brand-50 hover:text-brand-700 disabled:pointer-events-none disabled:opacity-30'

  return (
    <>
      <button
        ref={triggerRef}
        type="button"
        id={id}
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-invalid={invalid || undefined}
        disabled={disabled}
        onClick={() => setOpen((v) => !v)}
        className={cn(TRIGGER, 'cursor-pointer', open && TRIGGER_OPEN, invalid && 'border-rose-400', className)}
        {...rest}
      >
        <Calendar className="size-4 shrink-0 text-ink-400" aria-hidden />
        <span className={cn('min-w-0 flex-1 truncate', !selected && 'text-ink-400')}>{selected ? shown.format(selected) : placeholder}</span>
      </button>

      {open && createPortal(
        <div
          ref={panelRef}
          role="dialog"
          aria-label="Choose a date"
          onMouseDown={(e) => e.stopPropagation()}
          onClick={(e) => e.stopPropagation()}
          className="menu-pop fixed z-[80] w-[304px] max-w-[calc(100vw-16px)] overflow-hidden rounded-2xl border border-line bg-surface p-4 shadow-pop"
          style={position ? { top: position.top, left: position.left } : { top: 0, left: 0, visibility: 'hidden' }}
        >
          <div className="mb-3 flex items-center justify-between gap-2">
            {view === 'days' ? (
              <button type="button" onClick={() => setView('months')} className="flex items-center gap-1 rounded-lg px-2 py-1.5 text-[14px] font-bold text-ink-900 transition hover:bg-slate-100" aria-label="Choose month and year">
                {MONTHS[month.getMonth()]} {month.getFullYear()} <ChevronDown className="size-4 text-ink-400" />
              </button>
            ) : view === 'months' ? (
              <button type="button" onClick={() => { setDecade(Math.floor(month.getFullYear() / 12) * 12); setView('years') }} className="flex items-center gap-1 rounded-lg px-2 py-1.5 text-[14px] font-bold text-ink-900 transition hover:bg-slate-100" aria-label="Choose year">
                {month.getFullYear()} <ChevronDown className="size-4 text-ink-400" />
              </button>
            ) : (
              <span className="px-2 py-1.5 text-[14px] font-bold text-ink-900">{decade} – {decade + 11}</span>
            )}
            <div className="flex items-center gap-1">
              <button type="button" className={nav} disabled={prevDisabled} aria-label="Previous" onClick={() => (view === 'days' ? shiftMonth(-1) : view === 'months' ? setMonth(addMonths(month, -12)) : setDecade(decade - 12))}><ChevronLeft className="size-[18px]" /></button>
              <button type="button" className={nav} disabled={nextDisabled} aria-label="Next" onClick={() => (view === 'days' ? shiftMonth(1) : view === 'months' ? setMonth(addMonths(month, 12)) : setDecade(decade + 12))}><ChevronRight className="size-[18px]" /></button>
            </div>
          </div>

          {view === 'days' && (
            <div ref={gridRef} role="grid" aria-label={`${MONTHS[month.getMonth()]} ${month.getFullYear()}`} onKeyDown={onGridKey}>
              <div className="mb-1 grid grid-cols-7" role="row">
                {WEEKDAYS.map((d) => <div key={d} role="columnheader" className="py-1.5 text-center text-[11px] font-bold tracking-wide text-ink-400 uppercase">{d}</div>)}
              </div>
              <div className="grid grid-cols-7 gap-y-0.5" role="presentation">
                {cells.map((day) => {
                  const dayIso = toIso(day)
                  const inMonth = day.getMonth() === month.getMonth()
                  const isSelected = dayIso === selectedIso
                  const isToday = dayIso === todayIso
                  const isBlocked = blocked(day)
                  return (
                    <button
                      key={dayIso}
                      type="button"
                      role="gridcell"
                      data-iso={dayIso}
                      tabIndex={dayIso === toIso(focused) ? 0 : -1}
                      aria-selected={isSelected}
                      aria-current={isToday ? 'date' : undefined}
                      aria-label={day.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
                      disabled={isBlocked}
                      onClick={() => pick(day)}
                      className={cn(
                        'mx-auto flex size-9 items-center justify-center rounded-full text-[13px] transition-colors disabled:pointer-events-none disabled:opacity-30',
                        isSelected ? 'bg-brand-600 font-bold text-white shadow-sm shadow-brand-600/30'
                          : cn('hover:bg-brand-50 hover:text-brand-700', inMonth ? 'text-ink-900' : 'text-ink-400', isToday && 'font-bold text-brand-700 ring-1 ring-brand-300'),
                      )}
                    >
                      {day.getDate()}
                    </button>
                  )
                })}
              </div>
            </div>
          )}

          {view === 'months' && (
            <div className="grid grid-cols-3 gap-1.5">
              {MONTHS.map((name, i) => {
                const active = selected !== null && selected.getFullYear() === month.getFullYear() && selected.getMonth() === i
                return (
                  <button key={name} type="button" onClick={() => { const next = new Date(month.getFullYear(), i, 1); setMonth(next); setFocused(next); setView('days') }}
                    className={cn('rounded-xl py-2.5 text-[13px] font-semibold transition', active ? 'bg-brand-600 text-white' : 'text-ink-700 hover:bg-brand-50 hover:text-brand-700', !active && month.getMonth() === i && 'ring-1 ring-brand-300')}>
                    {name.slice(0, 3)}
                  </button>
                )
              })}
            </div>
          )}

          {view === 'years' && (
            <div className="grid grid-cols-3 gap-1.5">
              {Array.from({ length: 12 }, (_, i) => decade + i).map((year) => {
                const active = selected?.getFullYear() === year
                return (
                  <button key={year} type="button" onClick={() => { const next = new Date(year, month.getMonth(), 1); setMonth(next); setFocused(next); setView('months') }}
                    className={cn('rounded-xl py-2.5 text-[13px] font-semibold transition', active ? 'bg-brand-600 text-white' : 'text-ink-700 hover:bg-brand-50 hover:text-brand-700', !active && year === today.getFullYear() && 'ring-1 ring-brand-300')}>
                    {year}
                  </button>
                )
              })}
            </div>
          )}

          <div className="mt-3 flex items-center justify-between border-t border-line pt-3">
            <button type="button" disabled={blocked(today)} onClick={() => pick(today)} className="rounded-lg px-2.5 py-1.5 text-[13px] font-semibold text-brand-700 transition hover:bg-brand-50 disabled:opacity-40">Today</button>
            {clearable && selected && (
              <button type="button" onClick={() => { emit(''); setOpen(false); triggerRef.current?.focus() }} className="rounded-lg border border-line px-3 py-1.5 text-[13px] font-semibold text-ink-700 transition hover:bg-slate-50">Clear</button>
            )}
          </div>
        </div>,
        document.body,
      )}
    </>
  )
}
