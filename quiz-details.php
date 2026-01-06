<!DOCTYPE html>
<html lang="en">
  
<!-- Mirrored from softivuslab.com/html/quizio/live-demo/quiz-details.html by HTTrack Website Copier/3.x [XR&CO'2014], Wed, 31 Dec 2025 10:16:57 GMT -->
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link
      rel="shortcut icon"
      href="assets/images/logo.png"
      type="image/x-icon"
    />
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="manifest" href="manifest.json" />
    <title>Quiz Details - Quizio PWA HTML Template</title>
  <link href="style.css" rel="stylesheet"></head>
  <body class="">
    <div
      class="container min-h-dvh relative overflow-hidden py-8 dark:text-white dark:bg-color1"
    >
      <!-- Absolute Items Start -->
      <img
        src="assets/images/header-bg-1.png"
        alt=""
        class="absolute top-0 left-0 right-0 -mt-12"
      />
      <div
        class="absolute top-0 left-0 bg-p3 blur-[145px] h-[174px] w-[149px]"
      ></div>
      <div
        class="absolute top-40 right-0 bg-[#0ABAC9] blur-[150px] h-[174px] w-[91px]"
      ></div>
      <div
        class="absolute top-80 right-40 bg-p2 blur-[235px] h-[205px] w-[176px]"
      ></div>
      <div
        class="absolute bottom-0 right-0 bg-p3 blur-[220px] h-[174px] w-[149px]"
      ></div>
      <!-- Absolute Items End -->

      <!-- Page Title Start -->
      <div class="relative z-10 px-6">
        <div class="flex justify-between items-center gap-4">
          <div class="flex justify-start items-center gap-4">
            <a
              href="home.php"
              class="bg-white size-8 rounded-full flex justify-center items-center text-xl dark:bg-color10"
            >
              <i class="ph ph-caret-left"></i>
            </a>
            <h2 class="text-2xl font-semibold text-white">Contest Details</h2>
          </div>
        </div>
        <!-- Page Title End -->
        <div class="rounded-2xl overflow-hidden shadow2 mt-16">
          <div class="p-5 bg-white dark:bg-color10">
            <div class="flex justify-between items-center">
              <div class="flex justify-start items-center gap-2">
                <div
                  class="py-1 px-2 text-white bg-p2 rounded-lg dark:bg-p1 dark:text-black"
                >
                  <p class="font-semibold text-xs">19 Jun</p>
                  <p class="text-[10px]">04.32</p>
                </div>
                <div class="">
                  <p class="font-semibold text-xs">English Language Quiz</p>
                  <p class="text-xs">Language - English</p>
                </div>
              </div>
              <div class="flex justify-start items-center gap-1">
                <p
                  class="text-p2 text-[10px] py-0.5 px-1 bg-p2 bg-opacity-20 dark:text-p1 dark:bg-color24 rounded-md"
                >
                  05
                </p>
                <p class="text-p2 text-base font-semibold dark:text-p1">:</p>
                <p
                  class="text-p2 text-[10px] py-0.5 px-1 bg-p2 bg-opacity-20 dark:text-p1 dark:bg-color24 rounded-md"
                >
                  14
                </p>
                <p class="text-p2 text-base font-semibold dark:text-p1">:</p>
                <p
                  class="text-p2 text-[10px] py-0.5 px-1 bg-p2 bg-opacity-20 dark:text-p1 dark:bg-color24 rounded-md"
                >
                  20
                </p>
              </div>
            </div>

            <div
              class="flex justify-between items-center gap-2 text-xs py-3 text-nowrap mt-2"
            >
              <p>30 left</p>
              <div
                class="relative bg-p2 dark:bg-p1 dark:bg-opacity-10 bg-opacity-10 h-1 w-full rounded-full after:absolute after:h-1 after:w-[40%] after:bg-p2 after:dark:bg-p1 after:rounded-full"
              ></div>
              <p>100 spots</p>
            </div>
            <button
              class="py-3 text-center bg-p2 rounded-full text-sm font-semibold text-white block confirmationModalOpenButton w-full"
            >
              Join Now Rs. 125
            </button>

            <div
              class="pt-5 flex justify-between items-center border-t border-dashed border-black dark:border-color24 border-opacity-10 mt-5"
            >
              <div class="flex justify-start items-center gap-1">
                <i class="ph ph-trophy text-p1"></i>
                <p class="text-xs">1st Price - Rs. 500</p>
              </div>
              
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="hidden inset-0 z-40 confirmationModal">
      <div
        class="container bg-black dark:bg-white dark:bg-opacity-30 bg-opacity-40 flex justify-center items-center h-full px-6"
      >
        <div
          class="bg-white dark:bg-color10 p-5 rounded-xl w-full dark:text-white"
        >
          <div class="flex justify-between items-center pb-4">
            <p class="text-lg font-semibold">Confirmation</p>
            <button
              class="p-2 flex justify-center items-center rounded-full border border-color16 confirmationModalCloseButton dark:border-bgColor16"
            >
              <i class="ph ph-x"></i>
            </button>
          </div>
          <div
            class="py-4 border-y border-dashed border-color21 dark:border-color24"
          >
            <div class="flex justify-between items-center">
              <p class="text-color5 dark:text-bgColor5">Entry Fee :</p>
              <p class="font-semibold">Rs. 125</p>
            </div>
          </div>
          <div class="flex justify-between items-end py-4">
            <div class="">
              <p class="font-semibold">To Pay :</p>
              <p class="text-xs text-color5 dark:text-bgColor5">
                inclusive of taxes
              </p>
            </div>
            <p class="text-sm font-semibold text-p2 dark:text-p1">Rs. 125</p>
          </div>
          <a
            href="quiz-1.html"
            class="py-3 text-center bg-p2 rounded-full text-sm font-semibold text-white block w-full dark:bg-p1"
          >
            Join Now Rs. 125
          </a>
          <div class="flex justify-start items-start gap-2 pt-2">
            <div class="text-lg">
              <i class="ph ph-check-square"></i>
            </div>
            <p class="text-xs text-color5 dark:text-bgColor5">
              You agree to all terms & conditions and also agree to be contacted
              by company and their pertners
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="hidden inset-0 z-40 setReminderModal">
      <div
        class="container bg-black dark:bg-white dark:bg-opacity-30 bg-opacity-40 flex justify-center items-center h-full px-6"
      >
        <div
          class="bg-white dark:bg-color10 p-5 rounded-xl w-full dark:text-white"
        >
          <div
            class="flex justify-between items-center pb-4 border-b border-dashed border-color21 dark:border-color24"
          >
            <p class="text-lg font-semibold">Set Reminder</p>
            <button
              class="p-2 flex justify-center items-center rounded-full border border-color16 setReminderModalCloseButton dark:border-bgColor16"
            >
              <i class="ph ph-x"></i>
            </button>
          </div>

          <p class="text-xs text-color5 dark:text-bgColor5 py-4">
            You agree to all terms & conditions and also agree to be contacted
            by company and their partners
          </p>

          <div class="flex justify-between items-center gap-3">
            <button
              class="py-3 text-center border-color16 bg-color14 rounded-full text-sm font-semibold text-p2 dark:text-p1 block w-full dark:border-bgColor16 dark:bg-bgColor14"
            >
              Later
            </button>
            <button
              class="py-3 text-center bg-p2 rounded-full text-sm font-semibold text-white block w-full dark:bg-p1"
            >
              Set Now
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Javascript Dependecies -->
    <script src="assets/js/main.js"></script>
  <script defer src="index.js"></script></body>

<!-- Mirrored from softivuslab.com/html/quizio/live-demo/quiz-details.html by HTTrack Website Copier/3.x [XR&CO'2014], Wed, 31 Dec 2025 10:16:58 GMT -->
</html>
