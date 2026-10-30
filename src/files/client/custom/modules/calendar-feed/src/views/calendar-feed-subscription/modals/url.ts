import ModalView from 'views/modal';
import Ui from 'ui';

class SubscriptionUrlModalView extends ModalView {

    // language=Handlebars
    templateContent = `
        <p>{{message}}</p>
        <div class="input-group">
            <input type="text" class="form-control" data-name="url" value="{{url}}" readonly>
            <span class="input-group-btn">
                <button type="button" class="btn btn-default" data-action="copy">{{copyLabel}}</button>
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
        const value = input ? input.value : this.options.url;

        const done = () => {
            Ui.success(this.translate('urlCopied', 'messages', 'CalendarFeedSubscription'));
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done);

            return;
        }

        if (input) {
            input.select();
            document.execCommand('copy');
        }

        done();
    }
}

export default SubscriptionUrlModalView;
