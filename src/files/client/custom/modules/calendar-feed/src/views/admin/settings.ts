import View from 'view';
import Ajax from 'ajax';
import Ui from 'ui';

class CalendarFeedSettingsView extends View {

    // language=Handlebars
    templateContent = `
        <div class="page-header">
            <h3>{{title}}</h3>
        </div>
        <p class="text-muted">{{intro}}</p>
        <div class="panel panel-default">
            <div class="panel-body">
                <div class="row">
                    <div class="cell form-group col-sm-6">
                        <label class="control-label">{{pastLabel}}</label>
                        <input
                            type="number"
                            class="form-control"
                            data-name="pastMonths"
                            min="0"
                            max="120"
                            value="{{pastMonths}}"
                        >
                    </div>
                    <div class="cell form-group col-sm-6">
                        <label class="control-label">{{futureLabel}}</label>
                        <input
                            type="number"
                            class="form-control"
                            data-name="futureMonths"
                            min="0"
                            max="120"
                            value="{{futureMonths}}"
                        >
                    </div>
                </div>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" data-name="includeNotHeld" {{#if includeNotHeld}}checked{{/if}}>
                        {{includeLabel}}
                    </label>
                </div>
                <button type="button" class="btn btn-primary" data-action="save">{{saveLabel}}</button>
            </div>
        </div>
    `

    setup() {
        this.wait(
            Ajax.getRequest('CalendarFeed/settings').then(data => {
                this.pastMonths = data.pastMonths;
                this.futureMonths = data.futureMonths;
                this.includeNotHeld = data.includeNotHeld;
            })
        );
    }

    data() {
        return {
            title: this.translate('Calendar Feed Settings', 'labels', 'Admin'),
            intro: this.translate('settingsIntro', 'messages', 'CalendarFeed'),
            pastLabel: this.translate('pastMonths', 'fields', 'CalendarFeed'),
            futureLabel: this.translate('futureMonths', 'fields', 'CalendarFeed'),
            includeLabel: this.translate('includeNotHeld', 'fields', 'CalendarFeed'),
            saveLabel: this.translate('Save'),
            pastMonths: this.pastMonths,
            futureMonths: this.futureMonths,
            includeNotHeld: this.includeNotHeld,
        };
    }

    actionSave() {
        const pastMonths = parseInt(String(this.$el.find('[data-name="pastMonths"]').val()), 10);
        const futureMonths = parseInt(String(this.$el.find('[data-name="futureMonths"]').val()), 10);
        const includeNotHeld = this.$el.find('[data-name="includeNotHeld"]').is(':checked');

        Ui.notifyWait();

        Ajax.putRequest('CalendarFeed/settings', {
            pastMonths: pastMonths,
            futureMonths: futureMonths,
            includeNotHeld: includeNotHeld,
        }).then(data => {
            this.pastMonths = data.pastMonths;
            this.futureMonths = data.futureMonths;
            this.includeNotHeld = data.includeNotHeld;
            Ui.success(this.translate('Saved'));
        });
    }
}

export default CalendarFeedSettingsView;
