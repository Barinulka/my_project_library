import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        createUrl: String,
        historyUrlTemplate: String,
    };

    static targets = [
        'list',
        'cardTemplate',
        'status',
        'dialog',
        'form',
        'formError',
        'titleError',
        'authorError',
        'invNumberError',
        'submitButton',
        'history',
        'historyStatus',
        'historyList',
        'historyCardTemplate',
    ];

    isSubmitting = false;

    connect() {
        this.loadBooks();
    }

    async loadBooks() {
        this.statusTarget.textContent = 'Загрузка списка книг…';

        try {
            // Запрос к беку
            const response = await fetch(this.urlValue, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(
                    `Не удалось загрузить список книг: HTTP ${response.status}`,
                );
            }

            // Получение json-а
            const books = await response.json();

            // Отрисовка списка
            this.renderBooks(books)

            this.statusTarget.textContent = books.length === 0 ? 'Книг пока нет' : '';
        } catch (error) {
            console.error('Ошибка загрузки списка книг:', error);
            this.statusTarget.textContent = 'Не удалось загрузить список книг. Попробуйте обновить страницу.';
        }
    }

    renderBooks(books) {
        this.listTarget.replaceChildren();

        books.forEach((book) => {
            const template = this.cardTemplateTarget.content.cloneNode(true);
            template.querySelector('[data-field="title"]').textContent = book.title;
            template.querySelector('[data-field="author"]').textContent = book.author;
            template.querySelector('[data-field="inv_number"]').textContent = book.inv_number;

            const availability = template.querySelector('[data-field="availability"]');

            availability.textContent = book.available ? 'Доступна' : 'Выдана';
            availability.classList.add(book.available ? 'badge--available' : 'badge--unavailable',);

            const historyButton = template.querySelector('[data-field="historyButton"]');
            historyButton.dataset.bookId = book.id;

            this.listTarget.append(template);
        })
    }

    async createBook(event) {
        event.preventDefault();

        if (this.isSubmitting) {
            return;
        }

        const formData = new FormData(this.formTarget);

        const payload = {
            title: formData.get('title'),
            author: formData.get('author'),
            inv_number: formData.get('inv_number')
        };

        this.formErrorTarget.textContent = '';
        this.titleErrorTarget.textContent = '';
        this.authorErrorTarget.textContent = '';
        this.invNumberErrorTarget.textContent = '';

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
                this.formErrorTarget.textContent = data.message || 'Не удалось добавить книгу';

                this.showFieldErrors(data.errors ?? []);
                return;
            }

            this.formTarget.reset();
            this.dialogTarget.close();

            await this.loadBooks();
        } catch (error) {
            console.error('Ошибка добавления книги:', error);
            this.formErrorTarget.textContent = 'Не удалось получить ответ сервера. Проверьте список книг перед повторной отправкой.';
        } finally {
            this.isSubmitting = false;
            this.submitButtonTarget.disabled = false;
            this.submitButtonTarget.textContent = 'Добавить';
        }
    }

    showFieldErrors(errors) {
        const messages = {
            title: [],
            author: [],
            inv_number: [],
        };

        errors.forEach((error) => {
            if (messages[error.field]) {
                messages[error.field].push(error.message);
            }
        });

        this.titleErrorTarget.textContent = messages.title.join(' ');
        this.authorErrorTarget.textContent = messages.author.join(' ');
        this.invNumberErrorTarget.textContent = messages.inv_number.join(' ');
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

    onDialogCancel(event) {
        if (this.isSubmitting) {
            event.preventDefault();
        }
    }

    async historyBook(event) {
        const bookId = Number(event.currentTarget.dataset.bookId);

        const url = this.historyUrlTemplateValue.replace('__BOOK_ID__', String(bookId),);

        this.historyListTarget.replaceChildren();
        this.historyStatusTarget.textContent = 'Загрузка истории…';

        this.historyTarget.showModal();

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`Не удалось загрузить историю: HTTP ${response.status}`,);
            }

            const history = await response.json();

            this.renderHistory(history);

            this.historyStatusTarget.textContent = history.length === 0 ? 'Этот экземпляр ещё не выдавался' : '';
        } catch (error) {
            console.error('Ошибка загрузки истории:', error);
            this.historyStatusTarget.textContent = 'Не удалось загрузить историю. Закройте окно и попробуйте снова.';
        }
    }

    renderHistory(history) {
        this.historyListTarget.replaceChildren();

        history.forEach((loan) => {
            const card = this.historyCardTemplateTarget.content.cloneNode(true);

            card.querySelector('[data-field="client_name"]').textContent = loan.client_name;

            card.querySelector('[data-field="loan_at"]').textContent = new Date(loan.loan_at).toLocaleDateString('ru-RU');

            card.querySelector('[data-field="planned_return_at"]').textContent = new Date(loan.planned_return_at).toLocaleDateString('ru-RU');

            const returnStatus = card.querySelector('[data-field="return_status"]');

            returnStatus.textContent = loan.returned_at === null ? 'На руках' : `Возвращена: ${new Date(loan.returned_at).toLocaleDateString('ru-RU')}`;

            this.historyListTarget.append(card);
        });
    }

    closeHistory() {
        this.historyTarget.close();
    }
}
