exports.ids = [49];
exports.modules = {

/***/ 229:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _api_user__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(45);
/* harmony import */ var _api_login__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(44);


/* harmony default export */ __webpack_exports__["default"] = ({
  layout: 'user',
  head() {
    return {
      title: `${this.$t('user.cellphone')}-${this.$t('header.top.personal_center')}`
    };
  },
  data() {
    const validateCellphone = (rule, value, callback) => {
      if (value === '') {
        callback(new Error(this.$t('hint.error.import', {
          attribute: this.$t('find_password.cellphone')
        })));
      } else {
        const myreg = /^(((13[0-9]{1})|(15[0-9]{1})|(16[0-9]{1})|(18[0-9]{1})|(17[0-9]{1}))+\d{8})$/;
        if (!myreg.test(value)) {
          callback(new Error(this.$t('hint.error.wrong_format', {
            attribute: this.$t('find_password.cellphone')
          })));
        }
        callback();
      }
    };
    return {
      buttonLoading: false,
      loading: true,
      disabled: false,
      codename: this.$t('find_password.get_code'),
      seconds: '',
      cellphone: '',
      unit: '',
      user: {},
      ruleForm: {
        cellphone: '',
        code: '',
        state: 2
      },
      rules: {
        cellphone: [{
          required: true,
          message: this.$t('hint.error.import', {
            attribute: this.$t('cellphone.new')
          }),
          trigger: 'blur'
        }, {
          validator: validateCellphone,
          trigger: 'blur'
        }],
        code: [{
          required: true,
          message: this.$t('hint.error.import', {
            attribute: this.$t('find_password.verification_code')
          }),
          trigger: 'blur'
        }]
      }
    };
  },
  mounted() {
    this.getUser();
  },
  methods: {
    async getUser() {
      await Promise.all([Object(_api_user__WEBPACK_IMPORTED_MODULE_0__[/* detail */ "c"])(this.listQuery)]).then(([userData]) => {
        this.user = userData;
        this.cellphone = JSON.parse(JSON.stringify(userData.cellphone));
        this.loading = false;
      }).catch(error => {
        this.loading = false;
      });
    },
    // 获取验证码
    getCode() {
      const that = this;
      this.buttonLoading = true;
      Object(_api_login__WEBPACK_IMPORTED_MODULE_1__[/* cellphoneCode */ "b"])(this.ruleForm).then(response => {
        // 开始倒计时
        this.seconds = 60;
        this.codename = '';
        this.unit = 's';
        this.disabled = true;
        this.buttonLoading = false;
        this.timer = setInterval(function () {
          that.seconds = that.seconds - 1;
          if (that.seconds === 0) {
            // 读秒结束 清空计时器
            clearInterval(that.timer);
            that.seconds = '';
            that.codename = this.$t('find_password.get_code');
            that.unit = '';
            that.codeDisabled = false;
          }
        }, 1000);
        // 模拟验证码发送
        if (response.code) {
          that.ruleForm.code = response.code;
        }
      }).catch(() => {
        this.buttonLoading = false;
      });
    },
    submitForm() {
      this.$refs['ruleForm'].validate(valid => {
        if (valid) {
          this.loading = true;
          this.buttonLoading = true;
          Object(_api_login__WEBPACK_IMPORTED_MODULE_1__[/* changeCellphone */ "c"])(this.ruleForm).then(response => {
            this.loading = false;
            this.buttonLoading = false;
            this.getUser();
            this.$refs['ruleForm'].resetFields();
            this.$message({
              message: this.$t('common.success'),
              type: 'success'
            });
          }).catch(() => {
            this.loading = false;
            this.buttonLoading = false;
          });
        }
      });
    },
    resetForm(formName) {
      this.$refs[formName].resetFields();
    }
  }
});

/***/ })

};;
//# sourceMappingURL=cellphone.js.map