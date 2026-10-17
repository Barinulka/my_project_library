import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        returnUrl: String,
        clientsUrl: String,
        availableBooksUrl: String,
        issueUrl: String,
    };

    static targets = [
        'list',
        'cardTemplate',
        'status',
        'issueDialog',
        'issueForm',
        'issueError',
        'clientSelect',
        'bookSelect',
        'issueSubmit',
    ];

    isIssuing = false;

    connect() {
        this.loadLoans();
    }

    async loadLoans() {
        this.statusTarget.textContent = 'Загрузка выдач…';

        try {
            const response = await fetch(this.urlValue, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`Ошибка загрузки: HTTP ${response.status}`);
            }

            const loans = await response.json();

            this.renderLoans(loans);

            this.statusTarget.textContent = loans.length === 0 ? 'Открытых выдач пока нет' : '';
        } catch (error) {
            console.error('Ошибка загрузки выдач:', error);
            this.statusTarget.textContent = 'Не удалось загрузить выдачи. Попробуйте обновить страницу.';
        }
    }

    renderLoans(loans) {
        this.listTarget.replaceChildren();

        loans.forEach((loan) => {
            const card = this.cardTemplateTarget.content.cloneNode(true);

            card.querySelector('[data-field="title"]').textContent = loan.title;
            card.querySelector('[data-field="author"]').textContent = loan.author;
            card.querySelector('[data-field="inv_number"]').textContent = loan.inv_number;
            card.querySelector('[data-field="client_name"]').textContent = loan.client_name;

            card.querySelector('[data-field="loan_at"]').textContent =
                new Date(loan.loan_at).toLocaleDateString('ru-RU');

            card.querySelector('[data-field="planned_return_at"]').textContent =
                new Date(loan.planned_return_at).toLocaleDateString('ru-RU');

            const badge = card.querySelector('[data-field="overdue"]');

            badge.textContent = loan.overdue ? 'Просрочена' : 'Срок не истёк';
            badge.classList.add(loan.overdue ? 'badge--overdue' : 'badge--unavailable',);

            const returnButton = card.querySelector('[data-field="returnButton"]');
            returnButton.dataset.bookId = loan.book_id;

            this.listTarget.append(card);
        });
    }

    async returnBook(event) {
        const button = event.currentTarget;
        const bookId = Number(button.dataset.bookId);

        if (button.disabled) {
            return;
        }

        button.disabled = true;
        button.textContent = 'Возвращаем…';
        this.statusTarget.textContent = '';

        try {
            const response = await fetch(this.returnUrlValue, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    book_id: bookId,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                this.statusTarget.textContent = data.message || 'Не удалось оформить возврат';
                return;
            }

            await this.loadLoans();
        } catch (error) {
            console.error('Ошибка возврата:', error);
            this.statusTarget.textContent = 'Не удалось получить ответ сервера. Обновите список перед повторной попыткой.';
        } finally {
            button.disabled = false;
            button.textContent = 'Возврат';
        }
    }

    async openIssueDialog() {
        if (this.isIssuing) {
            return;
        }
        
        this.issueErrorTarget.textContent = 'Загрузка читателей и книг…';
        this.issueSubmitTarget.disabled = true;

        this.clientSelectTarget.replaceChildren(
            new Option('Выберите читателя', ''),
        );

        this.bookSelectTarget.replaceChildren(
            new Option('Выберите экземпляр', ''),
        );

        this.clientSelectTarget.disabled = true;
        this.bookSelectTarget.disabled = true;

        this.issueDialogTarget.showModal();

        try {
            const [clientsResponse, booksResponse] = await Promise.all([
                fetch(this.clientsUrlValue, {
                    headers: { Accept: 'application/json' },
                }),
                fetch(this.availableBooksUrlValue, {
                    headers: { Accept: 'application/json' },
                }),
            ]);

            if (!clientsResponse.ok || !booksResponse.ok) {
                throw new Error('Не удалось загрузить варианты выдачи');
            }

            const [clients, books] = await Promise.all([
                clientsResponse.json(),
                booksResponse.json(),
            ]);

            clients.forEach((client) => {
                this.clientSelectTarget.append(
                    new Option(
                        `${client.name} — ${client.email}`,
                        String(client.id),
                    ),
                );
            });

            books.forEach((book) => {
                this.bookSelectTarget.append(
                    new Option(
                        `${book.title} — ${book.inv_number}`,
                        String(book.id),
                    ),
                );
            });

            if (clients.length === 0) {
                this.issueErrorTarget.textContent = 'Сначала зарегистрируйте читателя.';
            } else if (books.length === 0) {
                this.issueErrorTarget.textContent = 'Нет доступных экземпляров для выдачи.';
            } else {
                this.issueErrorTarget.textContent = '';
                this.issueSubmitTarget.disabled = false;
            }
        } catch (error) {
            console.error('Ошибка загрузки вариантов выдачи:', error);

            this.issueErrorTarget.textContent = 'Не удалось загрузить читателей и книги. Закройте окно и попробуйте снова.';
        } finally {
            this.clientSelectTarget.disabled = false;
            this.bookSelectTarget.disabled = false;
        }
    }

    async issueLoan(event) {
        event.preventDefault();

        if (this.isIssuing || this.issueSubmitTarget.disabled) {
            return;
        }

        const formData = new FormData(this.issueFormTarget);

        const payload = {
            client_id: Number(formData.get('client_id')),
            book_id: Number(formData.get('book_id')),
        };

        this.isIssuing = true;
        this.issueErrorTarget.textContent = '';
        this.issueSubmitTarget.disabled = true;
        this.issueSubmitTarget.textContent = 'Выдаём…';

        try {
            const response = await fetch(this.issueUrlValue, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok) {
                this.issueErrorTarget.textContent = data.message || 'Не удалось выдать книгу';
                return;
            }

            this.issueFormTarget.reset();
            this.issueDialogTarget.close();

            await this.loadLoans();
        } catch (error) {
            console.error('Ошибка выдачи:', error);

            this.issueErrorTarget.textContent = 'Не удалось получить ответ сервера. Проверьте список выдач перед повторной отправкой.';
        } finally {
            this.isIssuing = false;
            this.issueSubmitTarget.disabled = false;
            this.issueSubmitTarget.textContent = 'Выдать';
        }
    }

    closeIssueDialog() {
        if (this.isIssuing) {
            return;
        }

        this.issueDialogTarget.close();
    }

    onIssueDialogCancel(event) {
        if (this.isIssuing) {
            event.preventDefault();
        }
    }
}
