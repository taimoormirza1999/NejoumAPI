<!DOCTYPE html>
<html lang="en">
    <?php use Illuminate\Support\Carbon; ?>

  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Payment Confirmation Email</title>
    <style media="all" type="text/css">
      @media all {
        .btn-primary table td:hover {
          background-color: #ec0867 !important;
        }

        .btn-primary a:hover {
          background-color: #ec0867 !important;
          border-color: #ec0867 !important;
        }
      }
      @media only screen and (max-width: 640px) {
        .main p,
        .main td,
        .main span {
          font-size: 16px !important;
        }

        .wrapper {
          padding: 8px !important;
        }

        .content {
          padding: 0 !important;
        }

        .container {
          padding: 0 !important;
          padding-top: 8px !important;
          width: 100% !important;
        }

        .main {
          border-left-width: 0 !important;
          border-radius: 0 !important;
          border-right-width: 0 !important;
        }

        .btn table {
          max-width: 100% !important;
          width: 100% !important;
        }

        .btn a {
          font-size: 16px !important;
          max-width: 100% !important;
          width: 100% !important;
        }
      }
      @media all {
        .ExternalClass {
          width: 100%;
        }

        .ExternalClass,
        .ExternalClass p,
        .ExternalClass span,
        .ExternalClass font,
        .ExternalClass td,
        .ExternalClass div {
          line-height: 100%;
        }

        .apple-link a {
          color: inherit !important;
          font-family: inherit !important;
          font-size: inherit !important;
          font-weight: inherit !important;
          line-height: inherit !important;
          text-decoration: none !important;
        }

        #MessageViewBody a {
          color: inherit;
          text-decoration: none;
          font-size: inherit;
          font-family: inherit;
          font-weight: inherit;
          line-height: inherit;
        }
      }
    </style>
  </head>
  <body
    style="
      font-family: Helvetica, sans-serif;
      -webkit-font-smoothing: antialiased;
      font-size: 16px;
      line-height: 1.3;
      -ms-text-size-adjust: 100%;
      -webkit-text-size-adjust: 100%;
      background-color: #f4f5f6;
      margin: 0;
      padding: 0;
    "
  >
    <table
      role="presentation"
      cellpadding="0"
      cellspacing="0"
      class="body"
      style="border-collapse: separate;
        mso-table-lspace: 0pt;
        mso-table-rspace: 0pt;
        background-color: #f4f5f6;
        width: 90%;
        margin:3% auto;
        overflow: hidden;
        "
    >
      <tr>
        <td
          style="
            font-family: Helvetica, sans-serif;
            font-size: 16px;
            vertical-align: top;
          "
          valign="top"
        >
          &nbsp;
        </td>
        <td
          class="container"
          style="
            font-family: Helvetica, sans-serif;
            font-size: 16px;
            vertical-align: top;
            max-width: 600px;
            padding: 0;
            padding-top: 24px;
            width: 600px;
            margin: 0 auto;
          "
          width="600"
          valign="top"
        >
          <div
            class="content"
            style="
              box-sizing: border-box;
              display: block;
              margin: 0 auto;
              max-width: 600px;
              padding: 0;
            "
          >
            <!-- START CENTERED WHITE CONTAINER -->
            <span
              class="preheader"
              style="
                color: transparent;
                display: none;
                height: 0;
                max-height: 0;
                max-width: 0;
                opacity: 0;
                overflow: hidden;
                mso-hide: all;
                visibility: hidden;
                width: 0;
              "
              >An email update regarding the payment confirmation having tracking id #<?=$transaction_id?>.</span
            >
            <table
              role="presentation"
              border="0"
              cellpadding="0"
              cellspacing="0"
              class="main"
              style="
                border-collapse: separate;
                mso-table-lspace: 0pt;
                mso-table-rspace: 0pt;
                background: #ffffff;
                border: 1px solid #eaebed;
                border-radius: 16px;
                width: 100%;
                margin 15px 0;
                 box-shadow:0 .5rem 1rem rgba(0,0,0,.15)!important
              "
              width="100%"
            >
              <!-- START MAIN CONTENT AREA -->
              <tr>
                <td
                  class="wrapper"
                  style="
                    font-family: Helvetica, sans-serif;
                    font-size: 16px;
                    vertical-align: top;
                    box-sizing: border-box;
                    padding: 2rem 2.2rem;
                    margin:4% 3%;
                    width:90%;
                    overflex-x:hidden;
                  "
                  valign="top"
                >
                <div style="margin: 5px auto; margin-bottom:10px; max-width: 150px;">
                    <img src="https://new.naj.ae/assets/images/logo-en.png" width="100%" height="auto" alt="logo" style="display: block; margin: 0 auto;">
                </div>
                  <p>Dear <?=$name?>,</p>

                  <p>
                    We are delighted to inform you that your payment has been successfully received. A payment of <b>AED&nbsp;<?= $amount ?></b> has been processed against your account.
                </p>
                    <p>
                        Payment Details:
                        <ul>
                            <li><strong>Amount:</strong> AED <?= $amount ?></li>
                            <li><strong>Payment Date:</strong> <?= Carbon::now()->format('Y-m-d H:i:s') ?>
</li>
                            <li><strong>Transaction ID:</strong> <?= $transaction_id ?></li>
                        </ul>
                    </p>

                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
                    <p style="direction:rtl;text-align:right;">
                        عزيزي [اسم العميل]،
                    </p>
                    <p style="direction:rtl;text-align:right;">
                        يسعدنا أن نخبركم بأن الدفع الخاص بك قد تم استلامه بنجاح. تمت معالجة دفعة بمبلغ <b>AED&nbsp;<?= $amount ?></b> من حسابك.
                    </p>
                    <p style="direction:rtl;text-align:right;">
                        تفاصيل الدفع:
                        <ul style="direction:rtl;text-align:right;">
                            <li><strong>المبلغ:</strong> AED<?= $amount ?></li>
                            <li><strong>تاريخ الدفع:</strong> <?= Carbon::now()->format('Y-m-d H:i:s') ?>
</li>
                            <li><strong>رقم المعاملة:</strong> <?= $transaction_id ?></li>
                        </ul>
                    </p>

                </td>
              </tr>

              <!-- END MAIN CONTENT AREA -->
            </table>

            <!-- START FOOTER -->
            <div
              class="footer"
              style="
                clear: both;
                padding-top: 24px;
                text-align: center;
                width: 100%;
              "
            >
              <table
                role="presentation"
                border="0"
                cellpadding="0"
                cellspacing="0"
                style="
                  border-collapse: separate;
                  mso-table-lspace: 0pt;
                  mso-table-rspace: 0pt;
                  width: 100%;
                "
                width="100%"
              >
                <tr>
                  <td
                    class="content-block"
                    style="
                      font-family: Helvetica, sans-serif;
                      vertical-align: top;
                      color: #9a9ea6;
                      font-size: 16px;
                      text-align: center;
                    "
                    valign="top"
                    align="center"
                  >
                    <span
                      class="apple-link"
                      style="
                        color: #9a9ea6;
                        font-size: 16px;
                        text-align: center;
                      "
                      >Nejoum Aljazeera Used Cars</span
                    >
                  </td>
                </tr>
                <tr>
                  <td
                    class="content-block powered-by"
                    style="
                      font-family: Helvetica, sans-serif;
                      vertical-align: top;
                      color: #9a9ea6;
                      font-size: 16px;
                      text-align: center;
                    "
                    valign="top"
                    align="center"
                  >
                    <a
                      href="https://naj.ae"
                      style="
                        color: #9a9ea6;
                        font-size: 16px;
                        text-align: center;
                        text-decoration: none;
                      "
                      >www.naj.ae</a
                    >
                  </td>
                </tr>
              </table>
            </div>
          </div>
        </td>
        <td
          style="
            font-family: Helvetica, sans-serif;
            font-size: 16px;
            vertical-align: top;
          "
          valign="top"
        >
          &nbsp;
        </td>
      </tr>
    </table>
  </body>
</html>
