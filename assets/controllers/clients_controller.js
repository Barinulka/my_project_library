import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        createUrl: String,
    };

    static targets = ['list', 'cardTemplate', 'status', 'dialog', 'form', 'formError', 'nameError', 'emailError', 'submitButton'];

    isSubmitting = false;

    connect() {
        this.loadClients();
    }

    async loadClients() {
        this.statusTarget.textContent = 'Загрузка читателей…';

        try {
            const response = await fetch(this.urlValue, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(
                    `Не удалось загрузить читателей: HTTP ${response.status}`,
                );
            }

            const clients = await response.json();

            this.renderClients(clients)

            this.statusTarget.textContent = clients.length === 0 ? 'Читателей пока нет' : '';

        } catch (error) {
            console.error('Ошибка загрузки читателей:', error);
            this.statusTarget.textContent = 'Не удалось загрузить читателей. Попробуйте обновить страницу.';
        }
    }

    renderClients(clients) {
        this.listTarget.replaceChildren();

        clients.forEach((client) => {
            const template = this.cardTemplateTarget.content.cloneNode(true);
            template.querySelector('[data-field="name"]').textContent = client.name;
            template.querySelector('[data-field="email"]').textContent = client.email;

            this.listTarget.append(template);
        })
    }

    openDialog() {
        this.dialogTarget.showModal();
    }

    closeDialog() {
        if (this.isSubmitting) {
            return;
        }

        this.dialogTarget.close();
    }

    async createClient(event) {
        event.preventDefault();

        if (this.isSubmitting) {
            return;
        }

        const formData = new FormData(this.formTarget);

        const payload = {
            name: formData.get('name'),
            email: formData.get('email'),
        };

        this.formErrorTarget.textContent = '';
        this.nameErrorTarget.textContent = '';
        this.emailErrorTarget.textContent = '';

        this.isSubmitting = true;
        this.submitButtonTarget.disabled = true;
        this.submitButtonTarget.textContent = 'Добавляем…';

        try {
            const response = await fetch(this.createUrlValue, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok) {
                this.formErrorTarget.textContent = data.message || 'Не удалось добавить читателя';

                this.showFieldErrors(data.errors ?? []);
                return;
            }

            this.formTarget.reset();
            this.dialogTarget.close();

            await this.loadClients();
        } catch (error) {
            console.error('Ошибка регистрации читателя:', error);
            this.formErrorTarget.textContent = 'Не удалось получить ответ сервера. Проверьте список читателей перед повторной отправкой.';
        } finally {
            this.isSubmitting = false;
            this.submitButtonTarget.disabled = false;
            this.submitButtonTarget.textContent = 'Добавить';
        }
    }

    showFieldErrors(errors) {
        const messages = {
            name: [],
            email: [],
        };

        errors.forEach((error) => {
            if (messages[error.field]) {
                messages[error.field].push(error.message);
            }
        });

        this.nameErrorTarget.textContent = messages.name.join(' ');
        this.emailErrorTarget.textContent = messages.email.join(' ');
    }

    onDialogCancel(event) {
        if (this.isSubmitting) {
            event.preventDefault();
        }
    }
}
