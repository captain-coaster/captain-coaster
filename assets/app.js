// Main CSS
import "./app.css"

// Import all images
import.meta.glob(["./images/**"])

const ThemeManager = {
    defaultTheme: "system",

    init() {
        // Apply theme on init (in case inline script failed)
        const savedTheme = localStorage.theme || this.defaultTheme
        this.applyTheme(savedTheme)

        // Set up event listeners when DOM is ready
        window.addEventListener("DOMContentLoaded", () => {
            this.setupEventListeners()
        })
    },

    applyTheme(theme) {
        if (!theme || theme === "system") {
            document.documentElement.removeAttribute("data-theme")
        } else {
            document.documentElement.dataset.theme = theme
        }
    },

    setupEventListeners() {
        document.querySelectorAll("[data-theme-control]").forEach((control) => {
            control.addEventListener("click", () => {
                const newTheme = control.getAttribute("data-theme-control") || this.defaultTheme

                // Store theme
                localStorage.theme = newTheme

                // Apply theme
                this.applyTheme(newTheme)
            })
        })
    },
}

// Initialize on script load
ThemeManager.init()
