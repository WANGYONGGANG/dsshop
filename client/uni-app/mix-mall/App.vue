<script>
/**
 * vuex管理登陆状态，具体可以参考官方登陆模板示例
 */
import i18n from '@/utils/lang/index'
import { mapMutations } from 'vuex';
import { getPlatform,getLogin } from 'utils'
import Login from '@/api/login.js'
import store from '@/store'
import Vue from 'vue';
export default {
	globalData: {
		statusBarHeight: 0, // 状态导航栏高度
		navHeight: 0, // 总体高度
		navigationBarHeight: 0, // 导航栏高度(标题栏高度)
	},
	onLaunch: function(options) {
		// uni.clearStorage()
		this.setSecret(options);
		// #ifdef MP
		if (uni.getStorageSync('applyDsshopOpenid')) {
			this.checkSession();
		} else {
			getLogin();
		}
		// #endif
		this.setLanguage({code: uni.getStorageSync('language')})
		// 状态栏高度
		this.globalData.statusBarHeight = uni.getSystemInfoSync().statusBarHeight
		// #ifdef MP-WEIXIN
		// 获取微信胶囊的位置信息 width,height,top,right,left,bottom
		const custom = wx.getMenuButtonBoundingClientRect()
		// 导航栏高度(标题栏高度) = 胶囊高度 + (顶部距离 - 状态栏高度) * 2
		this.globalData.navigationBarHeight = custom.height + (custom.top - this.globalData.statusBarHeight) * 2
		// 总体高度 = 状态栏高度 + 导航栏高度
		this.globalData.navHeight = this.globalData.navigationBarHeight + this.globalData.statusBarHeight
		// #endif
	},
	methods: {
		...mapMutations(['login']),
		setSecret(options) {
			let userInfo = uni.getStorageSync('dsshopUserInfo') || '';
			if (userInfo && uni.getStorageSync('dsshopApplytoken')) {
				//更新登陆状态
				userInfo.update = 1
				this.login(userInfo)
			}
		},
		// 检测登录状态是否过期
		checkSession() {
			let that = this;
			uni.checkSession({
				success() {},
				fail() {
					getLogin();
				}
			});
		},
		// 获取购物车角标
		showDsshopCartNumber(){
			const dsshopCartNumber = uni.getStorageSync('dsshopCartList') ? Object.keys(uni.getStorageSync('dsshopCartList')).length + '' : ''
			if(dsshopCartNumber && dsshopCartNumber != '0'){
				uni.setTabBarBadge({
				  index: 2,
				  text: dsshopCartNumber
				})
			}else{
				uni.removeTabBarBadge({
				  index: 2
				})
			}
		},
		// 设置底部tab
		setLanguage(res){
			// 首次加载无法获取缓存中的语言
			if(!res.code){
				res.code = i18n.locale
			}
			uni.setStorageSync('language', res.code, 31536000)
			i18n.locale = res.code
			// 设置底部tab
			uni.setTabBarItem({
				index: 0,
				text: i18n.t('tab_bar.0'),
				complete: (e)=>{
					console.log('e', e)
				}
			})
			uni.setTabBarItem({
				index: 1,
				text: i18n.t('tab_bar.1')
			})
			uni.setTabBarItem({
				index: 2,
				text: i18n.t('tab_bar.2')
			})
			uni.setTabBarItem({
				index: 3,
				text: i18n.t('tab_bar.3')
			})
		}
	},
	onShow: function() {
		this.showDsshopCartNumber()
		uni.addInterceptor('request', {
			invoke(args) {
			    if(args.url.indexOf('refreshToken') === -1){
			    	if(uni.getStorageSync('dsshopExpiresIn') && !store.state.refresh){
			    		if ((new Date()).getTime() >= uni.getStorageSync('dsshopExpiresIn') - 300 * 1000) { // token失效前5分钟会自动刷新token
							store.commit('setRefresh',true)
							// 计时器的作用是防止刷新token早于其它接口触发前触发导致接口数据获取失效，可用vue-router或是服务端配置token的时间
							setTimeout(function(){
								Login.refreshToken({
									refresh_token: uni.getStorageSync('dsshopRefreshToken')
								},function(res){
									store.commit('login',res)
									store.commit('setRefresh',false)
								})
							},5000);
			    			
			    		}
			    	}
			    }
			}
		})
	},
	onHide: function() {},
};
</script>
<style>
@import 'colorui/main.css';
@import 'colorui/icon.css';
</style>
<style lang="scss">
uni-rich-text img {
	max-width: 100% !important;
}
image {
	display: block; /*更改ColorUI中的默认值display: inline-block; 商品详情中图片会有空白间隙*/
}
/*
		全局公共样式和字体图标
	*/
@font-face {
	font-family: yticon;
	font-weight: normal;
	font-style: normal;
	src: url('https://at.alicdn.com/t/font_1078604_w4kpxh0rafi.ttf') format('truetype');
}

.yticon {
	font-family: 'yticon' !important;
	font-size: 16px;
	font-style: normal;
	-webkit-font-smoothing: antialiased;
	-moz-osx-font-smoothing: grayscale;
}

.icon-yiguoqi1:before {
	content: '\e700';
}

.icon-iconfontshanchu1:before {
	content: '\e619';
}

.icon-iconfontweixin:before {
	content: '\e611';
}

.icon-alipay:before {
	content: '\e636';
}

.icon-shang:before {
	content: '\e624';
}

.icon-shouye:before {
	content: '\e626';
}

.icon-shanchu4:before {
	content: '\e622';
}

.icon-xiaoxi:before {
	content: '\e618';
}

.icon-jiantour-copy:before {
	content: '\e600';
}

.icon-fenxiang2:before {
	content: '\e61e';
}

.icon-pingjia:before {
	content: '\e67b';
}

.icon-daifukuan:before {
	content: '\e68f';
}

.icon-pinglun-copy:before {
	content: '\e612';
}

.icon-dianhua-copy:before {
	content: '\e621';
}

.icon-shoucang:before {
	content: '\e645';
}

.icon-xuanzhong2:before {
	content: '\e62f';
}

.icon-gouwuche_:before {
	content: '\e630';
}

.icon-icon-test:before {
	content: '\e60c';
}

.icon-icon-test1:before {
	content: '\e632';
}

.icon-bianji:before {
	content: '\e646';
}

.icon-jiazailoading-A:before {
	content: '\e8fc';
}

.icon-zuoshang:before {
	content: '\e613';
}

.icon-jia2:before {
	content: '\e60a';
}

.icon-huifu:before {
	content: '\e68b';
}

.icon-sousuo:before {
	content: '\e7ce';
}

.icon-arrow-fine-up:before {
	content: '\e601';
}

.icon-hot:before {
	content: '\e60e';
}

.icon-lishijilu:before {
	content: '\e6b9';
}

.icon-zhengxinchaxun-zhifubaoceping-:before {
	content: '\e616';
}

.icon-naozhong:before {
	content: '\e64a';
}

.icon-xiatubiao--copy:before {
	content: '\e608';
}

.icon-shoucang_xuanzhongzhuangtai:before {
	content: '\e6a9';
}

.icon-jia1:before {
	content: '\e61c';
}

.icon-bangzhu1:before {
	content: '\e63d';
}

.icon-arrow-left-bottom:before {
	content: '\e602';
}

.icon-arrow-right-bottom:before {
	content: '\e603';
}

.icon-arrow-left-top:before {
	content: '\e604';
}

.icon-icon--:before {
	content: '\e744';
}

.icon-zuojiantou-up:before {
	content: '\e605';
}

.icon-xia:before {
	content: '\e62d';
}

.icon--jianhao:before {
	content: '\e60b';
}

.icon-weixinzhifu:before {
	content: '\e61a';
}

.icon-comment:before {
	content: '\e64f';
}

.icon-weixin:before {
	content: '\e61f';
}

.icon-fenlei1:before {
	content: '\e620';
}

.icon-erjiye-yucunkuan:before {
	content: '\e623';
}

.icon-Group-:before {
	content: '\e688';
}

.icon-you:before {
	content: '\e606';
}

.icon-forward:before {
	content: '\e607';
}

.icon-tuijian:before {
	content: '\e610';
}

.icon-bangzhu:before {
	content: '\e679';
}

.icon-share:before {
	content: '\e656';
}

.icon-yiguoqi:before {
	content: '\e997';
}

.icon-shezhi1:before {
	content: '\e61d';
}

.icon-fork:before {
	content: '\e61b';
}

.icon-kafei:before {
	content: '\e66a';
}

.icon-iLinkapp-:before {
	content: '\e654';
}

.icon-saomiao:before {
	content: '\e60d';
}

.icon-shezhi:before {
	content: '\e60f';
}

.icon-shouhoutuikuan:before {
	content: '\e631';
}

.icon-gouwuche:before {
	content: '\e609';
}

.icon-dizhi:before {
	content: '\e614';
}

.icon-fenlei:before {
	content: '\e706';
}

.icon-xingxing:before {
	content: '\e70b';
}

.icon-tuandui:before {
	content: '\e633';
}

.icon-zuanshi:before {
	content: '\e615';
}

.icon-zuo:before {
	content: '\e63c';
}

.icon-shoucang2:before {
	content: '\e62e';
}

.icon-shouhuodizhi:before {
	content: '\e712';
}

.icon-yishouhuo:before {
	content: '\e71a';
}

.icon-dianzan-ash:before {
	content: '\e617';
}

view,
scroll-view,
swiper,
swiper-item,
cover-view,
cover-image,
icon,
text,
rich-text,
progress,
button,
checkbox,
form,
input,
label,
radio,
slider,
switch,
textarea,
navigator,
audio,
camera,
image,
video {
	box-sizing: border-box;
}
/* 骨架屏替代方案 */
.Skeleton {
	background: #f3f3f3;
	padding: 20upx 0;
	border-radius: 8upx;
}

/* 图片载入替代方案 */
.image-wrapper {
	font-size: 0;
	background: #f3f3f3;
	border-radius: 4px;

	image {
		width: 100%;
		height: 100%;
		transition: 0.6s;
		opacity: 0;

		&.loaded {
			opacity: 1;
		}
	}
}

.clamp {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	display: block;
}

.common-hover {
	background: #f5f5f5;
}

/*边框*/
.b-b:after,
.b-t:after {
	position: absolute;
	z-index: 3;
	left: 0;
	right: 0;
	height: 0;
	content: '';
	transform: scaleY(0.5);
	border-bottom: 1px solid $border-color-base;
}

.b-b:after {
	bottom: 0;
}

.b-t:after {
	top: 0;
}

/* button样式改写 */
uni-button,
button {
	height: 80upx;
	line-height: 80upx;
	font-size: $font-lg + 2upx;
	font-weight: normal;

	&.no-border:before,
	&.no-border:after {
		border: 0;
	}
}

uni-button[type='default'],
button[type='default'] {
	color: $font-color-dark;
}

/* input 样式 */
.input-placeholder {
	color: #999999;
}

.placeholder {
	color: #999999;
}
/*  #ifdef  MP-ALIPAY  */
.cu-btn[disabled]{
	color: rgba(0,0,0,.3);
}
button-primary{
	border: none;
}
/*  #endif  */
/*  #ifdef  MP-BAIDU */
swan-uni-button[type='default'], swan-button[type='default'] {
   color:#FFFFFF;
}
/*  #endif  */
/*  #ifdef  MP-TOUTIAO */
uni-button[type='default'], tt-button[type='default']{
   color:#FFFFFF;
}
/*  #endif  */
</style>
