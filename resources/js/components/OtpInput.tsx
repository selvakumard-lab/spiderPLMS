import { useEffect, useRef, type ClipboardEvent, type KeyboardEvent } from 'react'
import { cn } from '@/lib/utils'

interface OtpInputProps {
  value: string
  onChange: (value: string) => void
  /** Called once all digits are entered, by typing the last digit or pasting the full code. */
  onComplete: (value: string) => void
  length?: number
  invalid?: boolean
  disabled?: boolean
  /** Changing this refocuses the first box (used after a wrong code). */
  resetKey?: number
}

/** One box per digit. Supports typing, backspace, arrow keys, and pasting a whole code. */
export function OtpInput({ value, onChange, onComplete, length = 6, invalid = false, disabled = false, resetKey = 0 }: OtpInputProps) {
  const refs = useRef<(HTMLInputElement | null)[]>([])
  const digits = Array.from({ length }, (_, i) => value[i] ?? '')

  useEffect(() => {
    refs.current[0]?.focus()
  }, [resetKey])

  const focusBox = (index: number) => {
    const box = refs.current[Math.max(0, Math.min(length - 1, index))]
    box?.focus()
    box?.select()
  }

  function commit(next: string[], focusIndex: number) {
    const joined = next.join('')
    onChange(joined)
    if (joined.length === length && next.every(Boolean)) {
      refs.current[focusIndex]?.blur()
      onComplete(joined)
    } else {
      focusBox(focusIndex)
    }
  }

  function handleChange(index: number, raw: string) {
    const entered = raw.replace(/\D/g, '')
    if (!entered) return
    // Several digits at once (autofill, or a browser that pastes into one box): spread them out.
    const next = [...digits]
    let cursor = index
    for (const ch of entered) {
      if (cursor >= length) break
      next[cursor++] = ch
    }
    commit(next, Math.min(cursor, length - 1))
  }

  function handleKeyDown(index: number, e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'Backspace') {
      e.preventDefault()
      const next = [...digits]
      if (next[index]) {
        next[index] = ''
        onChange(next.join(''))
      } else if (index > 0) {
        next[index - 1] = ''
        onChange(next.join(''))
        focusBox(index - 1)
      }
    } else if (e.key === 'ArrowLeft') {
      e.preventDefault()
      focusBox(index - 1)
    } else if (e.key === 'ArrowRight') {
      e.preventDefault()
      focusBox(index + 1)
    }
  }

  function handlePaste(e: ClipboardEvent<HTMLInputElement>) {
    e.preventDefault()
    const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, length)
    if (!pasted) return
    // A pasted code always replaces the whole field, whichever box it lands in.
    const next = Array.from({ length }, (_, i) => pasted[i] ?? '')
    commit(next, Math.min(pasted.length, length) - 1)
  }

  return (
    <div className={cn('flex justify-center gap-2 sm:gap-3', invalid && 'motion-safe:animate-shake')} role="group" aria-label="One-time code">
      {digits.map((digit, i) => (
        <input
          key={i}
          ref={(el) => {
            refs.current[i] = el
          }}
          type="text"
          inputMode="numeric"
          pattern="[0-9]*"
          autoComplete={i === 0 ? 'one-time-code' : 'off'}
          aria-label={`Digit ${i + 1} of ${length}`}
          maxLength={length}
          value={digit}
          disabled={disabled}
          onChange={(e) => handleChange(i, e.target.value)}
          onKeyDown={(e) => handleKeyDown(i, e)}
          onPaste={handlePaste}
          onFocus={(e) => e.target.select()}
          className={cn(
            'size-12 rounded-xl border bg-surface text-center text-xl font-bold text-ink-900 shadow-card transition duration-150 sm:size-14 sm:text-2xl',
            'focus:border-brand-500 focus:ring-4 focus:ring-brand-100 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60',
            invalid ? 'border-rose-400 bg-rose-50 text-rose-700' : digit ? 'border-brand-400' : 'border-line',
          )}
        />
      ))}
    </div>
  )
}
