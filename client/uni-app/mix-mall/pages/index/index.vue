<template>
	<view class="container">
		<!-- 导航 -->
		<view class="fixed cu-bar search bg-white" :style="{paddingTop:navHeight+'px',paddingBottom:'10px'}">
			<view @click="navTo('/pages/search/index')" class="search-form round">
				<text class="cuIcon-search"></text>
				<input disabled :adjust-position="false" type="text" :placeholder="$t('category.search')" confirm-type="search"></input>
			</view>
			<view @click="navTo('/pages/notice/notice')" class="action">
				<view class='cuIcon cuIcon-notice'>
					<view v-if="is_notice" class='cu-tag badge'></view>
				</view>
			</view>
		</view>
		<!-- 头部轮播 -->
		<view class="carousel-section" :style="{paddingTop: (navHeight+50)+'px'}">
			<!-- 标题栏和状态栏占位符 -->
			<view class="titleNview-placing"></view>
			<!-- 背景色区域 -->
			<view class="titleNview-background"></view>
			<swiper class="carousel" circular @change="swiperChange">
				<swiper-item v-for="(item, index) in carouselList" :key="index" class="carousel-item" @click="navToWwiperPage({item})">
					<image :src="item.resources.img"/>
				</swiper-item>
			</swiper>
			<!-- 自定义swiper指示器 -->
			<view class="swiper-dots">
				<text class="num">{{swiperCurrent+1}}</text>
				<text class="sign">/</text>
				<text class="num">{{swiperLength}}</text>
			</view>
		</view>
		<!-- 分类 -->
		<view class="cate-section" v-if="ctegory.length">
			<view v-for="item in ctegory" :key="item.id" class="cate-item" @click="navTo('/pages/product/list?fid='+(item.category ? item.category.pid : 0)+'&sid='+item.pid+'&tid='+item.id)">
				<image v-if="item.resources" :src="item.resources.img | smallImage(80)" lazy-load style="padding:20rpx;"></image>
				<text>{{item.name}}</text>
			</view>
		</view>
		<view class="ad-1" v-if="adData.resources">
			<image :src="adData.resources.img" mode="scaleToFill" lazy-load  @click="navTo(adData.url)"></image>
		</view>
		<!-- 为你推荐 -->
		<view class="f-header m-t" v-if="goodsList.length">
			<image src="/static/temp/h1.png"></image>
			<view class="tit-box">
				<text class="tit">{{$t('index.guidance.recommend')}}</text>
				<text class="tit2">Recommend To You</text>
			</view>
		</view>
		
		<view class="guess-section">
			<view 
				v-for="(item, index) in goodsList" :key="index"
				class="guess-item"
				@click="navToDetailPage(item)"
			>
				<view class="image-wrapper">
					<image :src="item.resources.img | smallImage(250)" mode="aspectFill" lazy-load></image>
				</view>
				<text class="title clamp">{{item.name}}</text>
				<text class="price">{{$t('common.unit')}}{{item.order_price | 1000}}</text>
			</view>
		</view>
		

	</view>
</template>

<script>
import Good from '@/api/good'
import Banner from '@/api/banner'
import Notification from '@/api/notification'
import { mapState } from 'vuex';
export default {
		data() {
			return {
				modalName: null,
				wechat: null,
				guidanceMy: false,
				titleNViewBackground: '',
				swiperCurrent: 0,
				swiperLength: 0,
				carouselList: [],
				goodsList: [],
				adData: {},
				ctegory:[],
				is_notice: false,
				navHeight: getApp().globalData.navHeight ? getApp().globalData.navHeight : 10,
			};
		},
		computed: {
			...mapState(['hasLogin'])
		},
		onLoad() {
			this.loadData()
			if(this.hasLogin){
				this.notice()
			}
			// #ifdef MP-WEIXIN 
			this.wechat=uni.getStorageSync('dsshopUserInfo').wechat
			// #endif
			if(!uni.getStorageSync('applyDsshopGuidanceMy')){
				this.guidanceMy = true
			}
		},
		onShow(){
			getApp().showDsshopCartNumber()
		},
		methods: {
			/**
			 * 请求数据
			 */
			async loadData() {
				const that = this
				// 轮播
				await Banner.getList({
					limit: 5,
					type: 0,
					state: 0,
					sort: '+sort'
				},function(res){
					that.carouselList = res.data
					that.swiperLength = res.data.length
				})
				// 首页广告
				await Banner.getList({
					type: 1,
					limit: 1,
					state: 0,
					sort: '+sort'
				},function(res){
					that.adData = res.data[0]
				})
				// 推荐商品
				await Good.getList({
					limit: 10,
					is_recommend: 1,
				},function(res){
					that.goodsList = res.data
				})
				// 推荐分类
				await Good.goodCategory({
					is_recommend: 1
				},function(res){
					that.ctegory = res
				})
			},
			//轮播图切换修改背景色
			swiperChange(e) {
				const index = e.detail.current;
				this.swiperCurrent = index;
			},
			//轮播跳转
			navToWwiperPage(item) {
				if(item.item.url){
					uni.navigateTo({
						url: item.item.url
					})
				}
			},
			//详情页
			navToDetailPage(item) {
				//测试数据没有写id，用title代替
				let id = item.id;
				uni.navigateTo({
					url: `/pages/product/detail?id=${id}`
				})
			},
			//跳转
			navTo(url){
				if(url){
					uni.navigateTo({
						url
					})  
				}
			},
			notice(){
				const that = this
				Notification.unread({},function(res){
					that.is_notice = res ? true : false
				})
			},
		}
		
	}
</script>

<style lang="scss">
	.mp-search-box{
		position:absolute;
		left: 0;
		top: 30upx;
		z-index: 9999;
		width: 100%;
		padding: 0 80upx;
		.ser-input{
			flex:1;
			height: 56upx;
			line-height: 56upx;
			text-align: center;
			font-size: 28upx;
			color:$font-color-base;
			border-radius: 20px;
			background: rgba(255,255,255,.6);
		}
	}
	page{
		.cate-section{
			position:relative;
			z-index:5;
			border-radius:16upx 16upx 0 0;
			margin-top:-20upx;
		}
		.carousel-section{
			padding: 0;
			.titleNview-placing {
				padding-top: 0;
				height: 0;
			}
			.carousel{
				.carousel-item{
					padding: 0;
				}
			}
			.swiper-dots{
				left:45upx;
				bottom:40upx;
			}
		}
	}
	
	page {
		background: #f5f5f5;
	}
	.m-t{
		margin-top: 16upx;
	}
	/* 头部 轮播图 */
	.carousel-section {
		position: relative;
		padding-top: 10px;

		.titleNview-placing {
			height: var(--status-bar-height);
			padding-top: 44px;
			box-sizing: content-box;
		}

		.titleNview-background {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 426upx;
			transition: .4s;
		}
	}
	.carousel {
		width: 100%;
		height: 350upx;

		.carousel-item {
			width: 100%;
			height: 100%;
			padding: 0 28upx;
			overflow: hidden;
		}

		image {
			width: 100%;
			height: 100%;
			border-radius: 10upx;
		}
	}
	.swiper-dots {
		display: flex;
		position: absolute;
		left: 60upx;
		bottom: 15upx;
		width: 72upx;
		height: 36upx;
		background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAMgAAABkCAYAAADDhn8LAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyZpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMTMyIDc5LjE1OTI4NCwgMjAxNi8wNC8xOS0xMzoxMzo0MCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6OTk4MzlBNjE0NjU1MTFFOUExNjRFQ0I3RTQ0NEExQjMiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6OTk4MzlBNjA0NjU1MTFFOUExNjRFQ0I3RTQ0NEExQjMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENDIDIwMTcgKFdpbmRvd3MpIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6Q0E3RUNERkE0NjExMTFFOTg5NzI4MTM2Rjg0OUQwOEUiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6Q0E3RUNERkI0NjExMTFFOTg5NzI4MTM2Rjg0OUQwOEUiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz4Gh5BPAAACTUlEQVR42uzcQW7jQAwFUdN306l1uWwNww5kqdsmm6/2MwtVCp8CosQtP9vg/2+/gY+DRAMBgqnjIp2PaCxCLLldpPARRIiFj1yBbMV+cHZh9PURRLQNhY8kgWyL/WDtwujjI8hoE8rKLqb5CDJaRMJHokC6yKgSCR9JAukmokIknCQJpLOIrJFwMsBJELFcKHwM9BFkLBMKFxNcBCHlQ+FhoocgpVwwnv0Xn30QBJGMC0QcaBVJiAMiec/dcwKuL4j1QMsVCXFAJE4s4NQA3K/8Y6DzO4g40P7UcmIBJxbEesCKWBDg8wWxHrAiFgT4fEGsB/CwIhYE+AeBAAdPLOcV8HRmWRDAiQVcO7GcV8CLM8uCAE4sQCDAlHcQ7x+ABQEEAggEEAggEEAggEAAgQACASAQQCCAQACBAAIBBAIIBBAIIBBAIABe4e9iAe/xd7EAJxYgEGDeO4j3EODp/cOCAE4sYMyJ5cwCHs4rCwI4sYBxJ5YzC84rCwKcXxArAuthQYDzC2JF0H49LAhwYUGsCFqvx5EF2T07dMaJBetx4cRyaqFtHJ8EIhK0i8OJBQxcECuCVutxJhCRoE0cZwMRyRcFefa/ffZBVPogePihhyCnbBhcfMFFEFM+DD4m+ghSlgmDkwlOgpAl4+BkkJMgZdk4+EgaSCcpVX7bmY9kgXQQU+1TgE0c+QJZUUz1b2T4SBbIKmJW+3iMj2SBVBWz+leVfCQLpIqYbp8b85EskIxyfIOfK5Sf+wiCRJEsllQ+oqEkQfBxmD8BBgA5hVjXyrBNUQAAAABJRU5ErkJggg==);
		background-size: 100% 100%;

		.num {
			width: 36upx;
			height: 36upx;
			border-radius: 50px;
			font-size: 24upx;
			color: #fff;
			text-align: center;
			line-height: 36upx;
		}

		.sign {
			position: absolute;
			top: 0;
			left: 50%;
			line-height: 36upx;
			font-size: 12upx;
			color: #fff;
			transform: translateX(-50%);
		}
	}
	/* 分类 */
	.cate-section {
		display: flex;
		justify-content: space-around;
		align-items: center;
		flex-wrap:wrap;
		padding: 30upx 22upx; 
		background: #fff;
		.cate-item {
			display: flex;
			flex-direction: column;
			align-items: center;
			font-size: $font-sm + 2upx;
			color: $font-color-dark;
		}
		/* 原图标颜色太深,不想改图了,所以加了透明度 */
		image {
			width: 88upx;
			height: 88upx;
			margin-bottom: 14upx;
			border-radius: 50%;
			opacity: .7;
			box-shadow: 4upx 4upx 20upx rgba(0, 0, 0, 0.3);
		}
	}
	.ad-1{
		width: 100%;
		height: 140upx;
		padding: 20upx 0;
		background: #fff;
		image{
			width:100%;
			height: 100%; 
		}
	}
	
	.f-header{
		display:flex;
		align-items:center;
		height: 140upx;
		padding: 6upx 30upx 8upx;
		background: #fff;
		image{
			flex-shrink: 0;
			width: 80upx;
			height: 80upx;
			margin-right: 20upx;
		}
		.tit-box{
			flex: 1;
			display: flex;
			flex-direction: column;
		}
		.tit{
			font-size: $font-lg +2upx;
			color: #font-color-dark;
			line-height: 1.3;
		}
		.tit2{
			font-size: $font-sm;
			color: $font-color-light;
		}
		.icon-you{
			font-size: $font-lg +2upx;
			color: $font-color-light;
		}
	}
	/* 分类推荐楼层 */
	.hot-floor{
		width: 100%;
		overflow: hidden;
		margin-bottom: 20upx;
		.floor-img-box{
			width: 100%;
			height:320upx;
			position:relative;
			&:after{
				content: '';
				position:absolute;
				left: 0;
				top: 0;
				width: 100%;
				height: 100%;
				background: linear-gradient(rgba(255,255,255,.06) 30%, #f8f8f8);
			}
		}
		.floor-img{
			width: 100%;
			height: 100%;
		}
		.floor-list{
			white-space: nowrap;
			padding: 20upx;
			padding-right: 50upx;
			border-radius: 6upx;
			margin-top:-140upx;
			margin-left: 30upx;
			background: #fff;
			box-shadow: 1px 1px 5px rgba(0,0,0,.2);
			position: relative;
			z-index: 1;
		}
		.scoll-wrapper{
			display:flex;
			align-items: flex-start;
		}
		.floor-item{
			width: 180upx;
			margin-right: 20upx;
			font-size: $font-sm+2upx;
			color: $font-color-dark;
			line-height: 1.8;
			image{
				width: 180upx;
				height: 180upx;
				border-radius: 6upx;
			}
			.price{
				color: $uni-color-primary;
			}
		}
		.more{
			display:flex;
			align-items: center;
			justify-content: center;
			flex-direction: column;
			flex-shrink: 0;
			width: 180upx;
			height: 180upx;
			border-radius: 6upx;
			background: #f3f3f3;
			font-size: $font-base;
			color: $font-color-light;
			text:first-child{
				margin-bottom: 4upx;
			}
		}
	}
	/* 猜你喜欢 */
	.guess-section{
		display:flex;
		flex-wrap:wrap;
		padding: 0 30upx;
		background: #fff;
		.guess-item{
			display:flex;
			flex-direction: column;
			width: 48%;
			padding-bottom: 40upx;
			&:nth-child(2n+1){
				margin-right: 4%;
			}
		}
		.image-wrapper{
			width: 100%;
			height: 330upx;
			border-radius: 3px;
			overflow: hidden;
			image{
				width: 100%;
				height: 100%;
				opacity: 1;
			}
		}
		.title{
			font-size: $font-lg;
			color: $font-color-dark;
			line-height: 80upx;
		}
		.price{
			font-size: $font-lg;
			color: $uni-color-primary;
			line-height: 1;
		}
	}
	/* #ifdef MP-WEIXIN */
	.guidance-my{
		position: relative;
		background-color: #FFFFFF;
		.triangle-top{
			position: absolute;
			right: 120upx;
			top: -39upx;
			width: 0;
			height: 0;
			border: 20upx solid;
			border-color: transparent transparent #333333;
		}
		.icon{
			line-height: 40upx;
		}
	}
	.guidance-modal{
		position: relative;
		display: inline-block;
		margin-left: auto;
		margin-right: auto;
		top:40upx;
		width: 90%;
		max-width: 100%;
		background-color: #f8f8f8;
		-webkit-border-radius: 5px;
		border-radius: 10rpx;
		padding-bottom: 60upx;
		.title{
			border-top-left-radius: 10rpx;
			border-top-right-radius: 10rpx;
			padding-bottom: 40upx;
		}
		.min-title{
			padding-left: 120upx;
		}
		.triangle-top{
			position: absolute;
			right: 100upx;
			top: -39upx;
			width: 0;
			height: 0;
			border: 20upx solid;
			border-color: transparent transparent #e54d42;
			z-index: 1;
		}
	}
	.guidance-modal-close{
		position: absolute;
		width: 100%;
		bottom: -120upx;
		text-align: center;
		.cuIcon-roundclose{
			font-size: 80upx;
			color: #FFFFFF;
		}
	}
	/* #endif */
</style>
