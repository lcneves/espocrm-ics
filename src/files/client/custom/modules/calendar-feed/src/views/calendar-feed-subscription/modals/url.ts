import ModalView from 'views/modal';
import Ui from 'ui';

class SubscriptionUrlModalView extends ModalView {

    // language=Handlebars
    templateContent = `
        <p>{{message}}</p>
        <div class="input-group">
            <input type="text" class="form-control" data-name="url" value="{{url}}" readonly>
            <span class="input-group-btn">
                <button type="button" class="btn btn-default action" data-action="copy">{{copyLabel}}</button>
            </span>
        </div>
    `

    setup() {
        this.headerText = this.translate('subscriptionUrlTitle', 'messages', 'CalendarFeedSubscription');

        this.buttonList = [
            {
                name: 'close',
                label: 'Close',
                style: 'primary',
                onClick: () => this.close(),
            },
        ];

        super.setup();
    }

    data() {
        return {
            url: this.options.url,
            message: this.translate('subscriptionUrlOnce', 'messages', 'CalendarFeedSubscription'),
            copyLabel: this.translate('Copy', 'labels'),
        };
    }

    actionCopy() {
        const input = this.$el.find('[data-name="url"]').get(0);
        const value = input && input.value ? input.value : String(this.options.url || '');

        if (value === '') {
            return;
        }

        if (input && this.copyFromInput(input)) {
            Ui.success(this.translate('urlCopied', 'messages', 'CalendarFeedSubscription'));

            return;
        }

        const clipboard = navigator.clipboard;

        if (clipboard && clipboard.writeText) {
            clipboard.writeText(value).then(() => {
                Ui.success(this.translate('urlCopied', 'messages', 'CalendarFeedSubscription'));
            }).catch(() => {
                Ui.error(this.translate('urlCopyFailed', 'messages', 'CalendarFeedSubscription'));
            });

            return;
        }

        Ui.error(this.translate('urlCopyFailed', 'messages', 'CalendarFeedSubscription'));
    }

    /**
     * Copy while the click is still a user gesture. The async clipboard API
     * rejects in Firefox on macOS when that gesture has already ended.
     *
     * @param {HTMLInputElement} input
     * @return {boolean}
     */
    copyFromInput(input) {
        const wasReadOnly = input.readOnly;

        input.readOnly = false;
        input.focus();
        input.select();
        input.setSelectionRange(0, input.value.length);

        let copied = false;

        try {
            copied = document.execCommand('copy');
        } catch {
            copied = false;
        }

        input.readOnly = wasReadOnly;

        return copied;
    }
}

export default SubscriptionUrlModalView;
