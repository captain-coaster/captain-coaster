import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = {
    initialStep: { type: Number, default: 1 },
  }

  static targets = ['step1', 'step2', 'step3', 'dot1', 'dot2', 'dot3']

  connect() {
    this.showStep(this.initialStepValue)
  }

  showStep(n) {
    ;[this.step1Target, this.step2Target, this.step3Target].forEach((el, i) => {
      el.hidden = i + 1 !== n
    })
    this.#updateDots(n)
  }

  goBack() {
    this.showStep(1)
  }

  #updateDots(active) {
    ;[this.dot1Target, this.dot2Target, this.dot3Target].forEach((dot, i) => {
      const step = i + 1
      // Remove all state classes
      dot.classList.remove(
        'w-2', 'w-4', 'rounded-full', 'rounded-sm',
        'bg-white/25', 'bg-white/55', 'bg-cc-blue-500'
      )
      if (step < active) {
        // done
        dot.classList.add('w-2', 'rounded-full', 'bg-white/55')
      } else if (step === active) {
        // active
        dot.classList.add('w-4', 'rounded-sm', 'bg-cc-blue-500')
      } else {
        // upcoming
        dot.classList.add('w-2', 'rounded-full', 'bg-white/25')
      }
    })
  }
}
