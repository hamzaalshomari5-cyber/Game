<div class="modal" id="buyModal">
  <div class="modal-box">
    <h3 id="mName">اسم المنتج</h3>
    <div class="m-price" id="mPrice"></div>
    <p class="muted" id="mDesc"></p>
    <label>العدد</label>
    <div class="qty-row">
      <button type="button" onclick="qtyStep(-1)">−</button>
      <input type="number" id="mQty" value="1" min="1">
      <button type="button" onclick="qtyStep(1)">+</button>
    </div>
    <div id="mPlayerWrap">
      <label id="mPlayerLabel">ID اللاعب</label>
      <input type="text" id="mPlayer" placeholder="" oninput="resetVerify()">
      <div class="verify-box" id="mVerify" style="display:none"></div>
    </div>
    <div class="m-total">الإجمالي: <b id="mTotal"></b></div>
    <div class="m-msg" id="mMsg"></div>
    <div class="m-actions">
      <button class="btn ghost" onclick="closeBuy()">إلغاء</button>
      <button class="btn" id="mBuyBtn" onclick="submitBuy()">شراء</button>
    </div>
  </div>
</div>
