const MUTE_KEY = 'plms.sound.muted'

let context: AudioContext | null = null

function safe<T>(fn: () => T): T | null {
  try {
    return fn()
  } catch {
    return null
  }
}

export const soundPreference = {
  isMuted: (): boolean => safe(() => localStorage.getItem(MUTE_KEY) === '1') ?? false,
  setMuted: (muted: boolean) => safe(() => (muted ? localStorage.setItem(MUTE_KEY, '1') : localStorage.removeItem(MUTE_KEY))),
}

/** Browsers only allow sound after the person has interacted with the page, so unlock the audio on the first click or key press. */
export function unlockSound() {
  const resume = () => {
    const Ctor = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
    if (!Ctor) return
    context ??= new Ctor()
    if (context.state === 'suspended') void context.resume()
  }
  for (const event of ['pointerdown', 'keydown']) window.addEventListener(event, resume, { once: true, passive: true })
}

/** A short two-tone chime. Returns false when the browser blocked it or the user muted it. */
export function playAlertSound(force = false): boolean {
  if (!force && soundPreference.isMuted()) return false
  try {
    const Ctor = window.AudioContext ?? (window as unknown as { webkitAudioContext?: typeof AudioContext }).webkitAudioContext
    if (!Ctor) return false
    context ??= new Ctor()
    if (context.state !== 'running') {
      void context.resume()
      return false
    }
    const now = context.currentTime
    ;[880, 1318.5].forEach((frequency, i) => {
      const oscillator = context!.createOscillator()
      const gain = context!.createGain()
      oscillator.type = 'sine'
      oscillator.frequency.value = frequency
      gain.gain.setValueAtTime(0.0001, now + i * 0.16)
      gain.gain.exponentialRampToValueAtTime(0.25, now + i * 0.16 + 0.02)
      gain.gain.exponentialRampToValueAtTime(0.0001, now + i * 0.16 + 0.35)
      oscillator.connect(gain).connect(context!.destination)
      oscillator.start(now + i * 0.16)
      oscillator.stop(now + i * 0.16 + 0.4)
    })
    return true
  } catch {
    return false
  }
}
