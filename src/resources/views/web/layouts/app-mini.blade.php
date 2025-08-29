<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maintenance</title>
    <link rel="stylesheet" href="https://cdn.metroui.org.ua/current/metro.css">
    <link rel="stylesheet" href="https://cdn.metroui.org.ua/current/icons.css">
    <style>
        #page-content {
            display: flex;
            justify-content: center;
            align-items: center;
            background: url("/images/bg-light.avif") center no-repeat;
            background-size: cover;
        }

        .dark-side {
            #page-content {
                background: url("/images/bg-dark.avif") center no-repeat;
                background-size: cover;
            }
        }

        .avatar {
            width: 140px;
            height: 140px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 0 10px rgba(0, 0, 0, .1);

            span {
                font-size: 96px;
            }
        }

        .dark-side {
            .avatar {
                background: #6d7278;
            }
        }

        .other-user {
            position: absolute;
            bottom: 20px;
            left: 20px;
            display: flex;
            gap: 10px;
            flex-direction: column;

            .avatar {
                width: 36px;
                height: 36px;

                span {
                    font-size: 24px;
                }
            }
        }

        .system-options {
            position: absolute;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 0;
            flex-direction: row;
            flex-wrap: nowrap;
        }
    </style>
</head>

<body class="h-vh-100 w-vw-100 d-flex flex-column flex-justify-center flex-align-center">


    <div class="d-flex flex-column flex-align-items-center">
        <div class="avatar">
            <span class="mif-person-outline"></span>
        </div>

        <div class="mt-10">
            <form action="" class="d-flex flex-column flex-center">
                <div class="form-group">
                    <div class="input"><input type="text" data-role="input" placeholder="Username..."
                            data-clear-button="false" data-role-input="true" id="id-input-26" class="">
                        <div class="button-group"></div>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 6px">
                    <div class="input"><input type="password" data-role="input" placeholder="Password..."
                            data-custom-buttons="customButtons" data-clear-button="false" data-reveal-button="false"
                            data-role-input="true" id="id-input-27" class="">
                        <div class="button-group"><button class="button input-custom-button" tabindex="-1"
                                type="button"><span class="mif-arrow-right"></span></button></div>
                    </div>
                </div>
                <div class="reduce-1 form-group mt-10 flex-center">
                    <span class="c-pointer fg-white">Sign-in options</span>
                </div>
            </form>
        </div>
    </div>

    <div class="other-user">
        <div class="d-flex flex-align-items-center gap-2 c-pointer">
            <div class="avatar">
                <span class="mif-person-outline"></span>
            </div>
            <span class="reduce-2 fg-white">Guest</span>
        </div>
    </div>

    <div class="system-options">
        <button class="outline no-border reduce-2">ENG</button>
        <button class="square outline no-border"><span class="mif-network"></span></button>
        <button class="square outline no-border"><span class="mif-keyboard"></span></button>
        <button class="square outline no-border"><span class="mif-power"></span></button>
    </div>
    <script src="{{ asset('vendor/capsulecmdr/seat-osmm/js/metro.js') }}" defer></script>
    <script>
        
    </script>
</body>

</html>