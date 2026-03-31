// Gestionnaire des banners - Version corrigée pour modules ES6
class BannerManager {
    constructor() {
        // Ne pas attendre DOMContentLoaded ici car déjà géré dans main.js
        this.initBanners();
        this.checkUrlParameters();
        this.autoHideBanners();
    }

    // Vérifier les paramètres URL et afficher les banners correspondants
    checkUrlParameters() {
        const urlParams = new URLSearchParams(window.location.search);

        // Messages de succès pour les membres
        if (urlParams.get("profile_invite") === "success") {
            this.success(
                "Le compte profile a été créé. La personne va recevoir un email avec les informations de connexion.",
                "Compte créé avec succès !",
            );
            this.cleanUrl(["profile_invite"]);
        }

        // Messages d'erreur pour les membres
        if (urlParams.get("profile_invite") === "error") {
            const errorMessage =
                urlParams.get("message") || "Une erreur est survenue.";
            this.error(
                decodeURIComponent(errorMessage),
                "Erreur de création de compte",
            );
            this.cleanUrl(["profile_invite", "message"]);
        }

        // Messages de formulaire générique
        if (urlParams.get("form_success")) {
            this.success("Votre demande a été envoyée avec succès !");
            this.cleanUrl(["form_success"]);
        }

        if (urlParams.get("form_error")) {
            const errorMessage =
                urlParams.get("error_message") ||
                "Une erreur est survenue lors de l'envoi du formulaire.";
            this.error(decodeURIComponent(errorMessage));
            this.cleanUrl(["form_error", "error_message"]);
        }

        // Ajoutez d'autres paramètres selon vos besoins
        if (urlParams.get("contact_success")) {
            this.success("Votre message a été envoyé avec succès !");
            this.cleanUrl(["contact_success"]);
        }
    }

    // Nettoyer l'URL après affichage du message
    cleanUrl(paramsToRemove) {
        const url = new URL(window.location);
        paramsToRemove.forEach((param) => {
            url.searchParams.delete(param);
        });

        // Remplacer l'URL dans l'historique sans recharger la page
        window.history.replaceState({}, document.title, url.toString());
    }

    initBanners() {
        // Gérer les boutons de fermeture existants
        const dismissButtons = document.querySelectorAll(
            "[data-banner-dismiss]",
        );
        dismissButtons.forEach((button) => {
            button.addEventListener("click", (e) => {
                this.dismissBanner(e.target.closest("[data-banner]"));
            });
        });

        // Animation d'entrée pour les banners existants
        const banners = document.querySelectorAll("[data-banner]");
        banners.forEach((banner, index) => {
            setTimeout(() => {
                banner.style.animation = "slideInDown 0.4s ease-out forwards";
            }, index * 150);
        });
    }

    dismissBanner(banner) {
        if (!banner) return;

        banner.style.transition = "all 0.3s ease-out";
        banner.style.transform = "translateX(100%)";
        banner.style.opacity = "0";

        setTimeout(() => {
            banner.remove();
            this.adjustBannerContainer();
        }, 300);
    }

    autoHideBanners(delay = 10000) {
        // Masquer automatiquement les banners après X secondes (sauf erreurs)
        const banners = document.querySelectorAll(
            '[data-banner]:not([data-type="error"])',
        );
        banners.forEach((banner) => {
            setTimeout(() => {
                if (banner.parentNode) {
                    this.dismissBanner(banner);
                }
            }, delay);
        });
    }

    adjustBannerContainer() {
        const container = document.querySelector(".banner-container");
        const remainingBanners = container?.querySelectorAll("[data-banner]");

        if (container && (!remainingBanners || remainingBanners.length === 0)) {
            container.style.display = "none";
        }
    }

    createBanner(type, message, title = null, dismissible = true) {
        const container = this.getBannerContainer();

        const banner = document.createElement("div");
        banner.setAttribute("data-banner", "true");
        banner.setAttribute("data-type", type);
        banner.className = this.getBannerClasses(type);
        banner.setAttribute("role", "alert");

        banner.innerHTML = this.getBannerHTML(
            type,
            message,
            title,
            dismissible,
        );

        container.appendChild(banner);
        container.style.display = "block";

        this.initSingleBanner(banner);

        return banner;
    }

    getBannerContainer() {
        let container = document.querySelector(".banner-container");
        if (!container) {
            container = document.createElement("div");
            container.className = "banner-container";
            document.body.appendChild(container);
        }
        return container;
    }

    getBannerClasses(type) {
        return `banner banner--${type || "info"}`;
    }

    getBannerHTML(type, message, title, dismissible) {
        const icons = {
            success: "✓",
            error: "✕",
            warning: "⚠",
            info: "ℹ",
        };

        const titleHTML = title
            ? `<h3 class="banner__title">${title}</h3>`
            : "";

        const dismissButton = dismissible
            ? `
            <div class="banner__dismiss-wrap">
                <button data-banner-dismiss class="banner__dismiss">
                    <span class="sr-only">Fermer</span>
                    <svg class="banner__dismiss-icon" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </button>
            </div>`
            : "";

        return `
            <div class="banner__body">
                <div class="banner__icon-wrap">
                    <span class="banner__icon">${icons[type] || icons.info}</span>
                </div>
                <div class="banner__content">
                    ${titleHTML}
                    <div class="banner__message">${message}</div>
                </div>
            </div>
            ${dismissButton}
        `;
    }

    initSingleBanner(banner) {
        const dismissButton = banner.querySelector("[data-banner-dismiss]");
        if (dismissButton) {
            dismissButton.addEventListener("click", () => {
                this.dismissBanner(banner);
            });
        }

        banner.style.animation = "slideInDown 0.4s ease-out forwards";
    }

    // Méthodes de convenance
    success(message, title = null) {
        return this.createBanner("success", message, title);
    }

    error(message, title = null) {
        return this.createBanner("error", message, title);
    }

    warning(message, title = null) {
        return this.createBanner("warning", message, title);
    }

    info(message, title = null) {
        return this.createBanner("info", message, title);
    }
}

// Export de la fonction qui initialise le BannerManager
const bannerManagerInit = () => {
    // Initialiser le gestionnaire
    const bannerManager = new BannerManager();

    // Exposer globalement pour utilisation dans d'autres scripts
    window.BannerManager = bannerManager;

    // Retourner l'instance pour usage local si besoin
    return bannerManager;
};

export default bannerManagerInit;
export { BannerManager };
