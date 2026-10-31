import EditModalView from 'views/modals/edit';
import Ui from 'ui';

/**
 * Create dialog. The stock dialog closes as soon as save returns, which
 * removes a URL drawer opened from the form. This waits until that drawer
 * is dismissed.
 */
class SubscriptionEditModalView extends EditModalView {

    async actionSave(data = {}) {
        const editView = this.getRecordView();
        const model = editView.model;
        const wasNew = !this.id;

        if (!wasNew) {
            return super.actionSave(data);
        }

        const disabledButtons = [];

        this.buttonList.forEach(item => {
            if (!item.name || item.disabled) {
                return;
            }

            this.disableButton(item.name);
            disabledButtons.push(item.name);
        });

        try {
            await editView.save();
        } catch {
            disabledButtons.forEach(name => this.enableButton(name));

            return;
        }

        this.id = model.id;

        const url = model.get('subscriptionUrl');

        this.trigger('after:save', model, {bypassClose: true});

        if (url) {
            await this.showSubscriptionUrl(url);
        }

        if (!data.bypassClose) {
            this.close();
            Ui.success(this.translate('Created'));

            return;
        }

        disabledButtons.forEach(name => this.enableButton(name));
    }

    /**
     * @private
     * @param {string} url
     * @return {Promise<void>}
     */
    showSubscriptionUrl(url) {
        return new Promise(resolve => {
            let settled = false;

            const finish = () => {
                if (settled) {
                    return;
                }

                settled = true;
                resolve();
            };

            this.createView(
                'subscriptionUrl',
                'modules/calendar-feed/views/calendar-feed-subscription/modals/url',
                {url: url},
                view => {
                    view.render();
                    this.listenToOnce(view, 'close', finish);
                },
            );
        });
    }
}

export default SubscriptionEditModalView;
