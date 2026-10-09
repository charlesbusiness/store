{{-- A single reusable confirmation for destructive submits, opened by
     Stockly.confirmDanger(). A native <dialog> gives focus trapping, Esc to
     cancel and an inert page behind it without a dependency or a framework.
     Included once by the layout, so any form can opt in. --}}
<dialog class="dlg" id="stockly-confirm" aria-labelledby="stockly-confirm-title">
  <h2 id="stockly-confirm-title">Are you sure?</h2>
  <p id="stockly-confirm-body" class="mut"></p>

  <div class="row">
    <button class="btn alt" type="button" data-confirm-cancel>Cancel</button>
    <button class="btn danger" type="button" data-confirm-accept>Confirm</button>
  </div>
</dialog>
