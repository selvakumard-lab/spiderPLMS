import type { RazorpayResult, RegistrationOrder } from '@/lib/endpoints'

interface RazorpayInstance { open: () => void; on: (event: string, cb: (res: { error?: { description?: string } }) => void) => void }
declare global {
  interface Window { Razorpay?: new (options: Record<string, unknown>) => RazorpayInstance }
}

function loadCheckout(): Promise<void> {
  if (window.Razorpay) return Promise.resolve()
  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = 'https://checkout.razorpay.com/v1/checkout.js'
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('Could not load Razorpay. Check your connection and try again.'))
    document.head.appendChild(script)
  })
}

/** Opens Razorpay Checkout for an order. Resolves with the signed result, rejects if the payer closes it or it fails. */
export async function payWithRazorpay(order: RegistrationOrder, description: string): Promise<RazorpayResult> {
  await loadCheckout()
  return new Promise((resolve, reject) => {
    const checkout = new window.Razorpay!({
      key: order.key,
      order_id: order.order_id,
      amount: order.amount,
      currency: order.currency,
      name: 'SpiderPLMS',
      description,
      prefill: { name: order.name, email: order.email, contact: order.contact ?? undefined },
      theme: { color: '#d91920' },
      handler: (result: RazorpayResult) => resolve(result),
      modal: { ondismiss: () => reject(new Error('Payment was cancelled.')) },
    })
    checkout.on('payment.failed', (res) => reject(new Error(res.error?.description ?? 'Payment failed.')))
    checkout.open()
  })
}
