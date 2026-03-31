class ProfileRequestForm {
    constructor(formId = "profile-form") {
        this.form = document.getElementById(formId);
        this.apiUrl = profileFormData?.apiUrl;
        this.nonce = profileFormData?.nonce;
        this.messages = profileFormData?.messages;

        this.init();
    }

    init() {
        if (!this.form) {
            return;
        }

        if (!this.apiUrl || !this.nonce) {
            console.error("profileFormData is missing or incomplete");
            return;
        }

        this.bindEvents();
    }

    bindEvents() {
        this.form.addEventListener("submit", (e) => this.handleSubmit(e));
    }

    async handleSubmit(e) {
        e.preventDefault();

        const formData = new FormData(e.target);

        try {
            const response = await this.submitForm(formData);
            const result = await response.json();

            if (result.success) {
                this.handleSuccess(e.target);
            } else {
                this.handleError(result.message);
            }
        } catch (error) {
            console.error("Erreur:", error);
            this.handleUnexpectedError();
        }
    }

    async submitForm(formData) {
        return await fetch(this.apiUrl, {
            method: "POST",
            body: formData,
            headers: {
                "X-WP-Nonce": this.nonce,
            },
        });
    }

    handleSuccess(formElement) {
        if (typeof BannerManager !== "undefined") {
            BannerManager.success(this.messages?.success || "Succès");
        }

        formElement.reset();
    }

    handleError(message) {
        const errorMessage = `${this.messages?.error || "Erreur"}: ${message}`;

        if (typeof BannerManager !== "undefined") {
            BannerManager.error(errorMessage);
        }
    }

    handleUnexpectedError() {
        if (typeof BannerManager !== "undefined") {
            BannerManager.error(
                this.messages?.unexpected || "Erreur inattendue",
            );
        }
    }

    // Méthode pour détruire l'instance (optionnel)
    destroy() {
        if (this.form) {
            this.form.removeEventListener("submit", this.handleSubmit);
        }
    }

    // Méthodes utilitaires
    setApiUrl(url) {
        this.apiUrl = url;
        return this;
    }

    setNonce(nonce) {
        this.nonce = nonce;
        return this;
    }

    setMessages(messages) {
        this.messages = messages;
        return this;
    }
}

// Fonction factory pour maintenir la compatibilité
const profileRequestForm = (formId = "profile-form") => {
    return new ProfileRequestForm(formId);
};

export default profileRequestForm;
