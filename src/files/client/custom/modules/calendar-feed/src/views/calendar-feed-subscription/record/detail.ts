import DetailRecordView from 'views/record/detail';
import Ajax from 'ajax';
import Ui from 'ui';

class CalendarFeedSubscriptionDetailView extends DetailRecordView {

    setup() {
        super.setup();

        this.addDropdownItem({
            name: 'regenerateUrl',
            label: this.translate('Regenerate URL', 'labels', 'CalendarFeedSubscription'),
            action: 'regenerateUrl',
        });

        if (!this.model.get('isRevoked')) {
            this.addDropdownItem({
                name: 'revoke',
                label: this.translate('Revoke', 'labels', 'CalendarFeedSubscription'),
                action: 'revoke',
            });
        }
    }

    actionRegenerateUrl() {
        Ui.notifyWait();

        Ajax.postRequest('CalendarFeedSubscription/' + this.model.id + '/regenerate')
            .then(response => {
                Ui.notify(false);
                this.showUrl(response.subscriptionUrl);
            });
    }

    actionRevoke() {
        this.confirm(this.translate('revokeConfirm', 'messages', 'CalendarFeedSubscription'))
            .then(() => {
                Ui.notifyWait();

                return Ajax.postRequest('CalendarFeedSubscription/' + this.model.id + '/revoke');
            })
            .then(() => {
                Ui.success(this.translate('revoked', 'messages', 'CalendarFeedSubscription'));

                return this.model.fetch();
            })
            .then(() => this.reRender());
    }

    /**
     * @private
     * @param {string} url
     */
    showUrl(url) {
        this.createView('subscriptionUrl', 'modules/calendar-feed/views/calendar-feed-subscription/modals/url', {
            url: url,
        }, view => {
            view.render();
        });
    }
}

export default CalendarFeedSubscriptionDetailView;
