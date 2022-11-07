jQuery(function ($) {
  $(document).ready(function () {
    var id = wc_azampay_admin_params.id;
    var kycUrl = wc_azampay_admin_params.kycUrl;

    var testMode = $(`#woocommerce_${id}_test_mode`);

    var allowedPartners = $(`#woocommerce_${id}_allowed_partners`).closest(
      "tr"
    );

    var prodInstructions = `<tr valign="top">
                                  <th scope="row" class="titledesc">
                                    <label for="woocommerce_${id}_prod_instructions">
                                      Production Instructions
                                    </label>
                                  </th>
                                  <td id="woocommerce_${id}_prod_instructions">
                                    <fieldset>
                                      <legend class="screen-reader-text">
                                        <span>Click here to submit your KYC and get your live credentials.</span>
                                      </legend>
                                      <label for="woocommerce_${id}_prod_instructions">
                                        Click <a href="${kycUrl}" target="_blank">here</a> to submit your KYC and get your live credentials.
                                      </label>
                                      <br>
                                    </fieldset>
                                  </td>
                                </tr>`;

    var testInstructions = `<tr valign="top">
                                  <th scope="row" class="titledesc">
                                    <label for="woocommerce_${id}_test_instructions">
                                      Test Instructions
                                    </label>
                                  </th>
                                  <td id="woocommerce_${id}_test_instructions">
                                    <fieldset>
                                      <legend class="screen-reader-text">
                                        <span>Click here to register your websites/applications and get your sandbox credentials.</span>
                                      </legend>
                                      <label for="woocommerce_${id}_test_instructions">
                                        Click <a href="https://developers.azampay.co.tz/sandbox/registerapp" target="_blank">here</a> to register your websites/applications and get your sandbox credentials.
                                      </label>
                                      <br>
                                    </fieldset>
                                  </td>
                                </tr>`;

    $(allowedPartners).insertBefore(testMode.closest("tr"));
    $(testInstructions).insertAfter(testMode.closest("tr"));
    $(prodInstructions).insertAfter(testMode.closest("tr"));

    $(allowedPartners).removeAttr("style");

    var prodFields = $(
      `#woocommerce_${id}_prod_instructions, 
      #woocommerce_${id}_prod_app_name, 
      #woocommerce_${id}_prod_client_id, 
      #woocommerce_${id}_prod_client_secret, 
      #woocommerce_${id}_prod_callback_token`
    );

    var testFields = $(
      `#woocommerce_${id}_test_instructions, 
      #woocommerce_${id}_test_app_name,
      #woocommerce_${id}_test_client_id,
      #woocommerce_${id}_test_client_secret,
      #woocommerce_${id}_test_callback_token`
    );

    if (testMode.is(":checked")) {
      prodFields.closest("tr").hide();
    } else {
      testFields.closest("tr").hide();
    }

    testMode.change(() => {
      prodFields.closest("tr").toggle();
      testFields.closest("tr").toggle();
    });
  });
});
