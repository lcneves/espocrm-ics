import EditRecordView from 'views/record/edit';

class CalendarFeedSubscriptionEditView extends EditRecordView {

    afterSave() {
        const url = this.model.get('subscriptionUrl');
        const created = this.isNew;

        super.afterSave();

        if (created && url) {
            this.urlPromise = this.showSubscriptionUrl(url);
        }
    }

    async actionSave(data) {
        data = data || {};

        const created = this.isNew;

        try {
            await this.save(data.options);
        } catch (reason) {
            return await Promise.reject(reason);
        }

        if (this.urlPromise) {
            await this.urlPromise;
            this.urlPromise = null;
        }

        if (this.options.duplicateSourceId) {
            this.returnUrl = null;
        }

        this.exit(created ? 'create' : 'save');
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

export default CalendarFeedSubscriptionEditView;
