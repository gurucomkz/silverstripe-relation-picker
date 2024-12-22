<div class="form-group field readonly">
    <label class="form__field-label">$Title</label>
    <div class="form__field-holder">
        <div class="form-control-static readonly" style="word-break: break-all;">
            $Content.RAW
        </div>
        <% if $Description %>
            <i>$Description</i>
        <% end_if %>
    </div>
</div>
