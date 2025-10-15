<script setup>
const bubbleConfigs = [
  {
    id: 1,
    style: {
      '--size': '24rem',
      '--duration': '48s',
      '--delay': '-10s',
      '--start-left': '4%',
      '--start-bottom': '-24rem',
      '--move-x': '12vw',
      '--move-y': '-120vh',
      '--opacity': '0.42',
      '--scale': '1.12',
    },
  },
  {
    id: 2,
    style: {
      '--size': '16rem',
      '--duration': '34s',
      '--delay': '-18s',
      '--start-left': '18%',
      '--start-bottom': '-20rem',
      '--move-x': '-6vw',
      '--move-y': '-116vh',
      '--opacity': '0.56',
    },
  },
  {
    id: 3,
    style: {
      '--size': '14rem',
      '--duration': '30s',
      '--delay': '-6s',
      '--start-left': '32%',
      '--start-bottom': '-18rem',
      '--move-x': '9vw',
      '--move-y': '-118vh',
      '--opacity': '0.6',
      '--scale': '0.94',
    },
  },
  {
    id: 4,
    style: {
      '--size': '20rem',
      '--duration': '42s',
      '--delay': '-22s',
      '--start-left': '46%',
      '--start-bottom': '-26rem',
      '--move-x': '-8vw',
      '--move-y': '-124vh',
      '--opacity': '0.5',
      '--blur': '1px',
    },
  },
  {
    id: 5,
    style: {
      '--size': '15rem',
      '--duration': '32s',
      '--delay': '-14s',
      '--start-left': '61%',
      '--start-bottom': '-19rem',
      '--move-x': '7vw',
      '--move-y': '-118vh',
      '--opacity': '0.58',
    },
  },
  {
    id: 6,
    style: {
      '--size': '22rem',
      '--duration': '46s',
      '--delay': '-4s',
      '--start-left': '82%',
      '--start-bottom': '-25rem',
      '--move-x': '-11vw',
      '--move-y': '-122vh',
      '--opacity': '0.4',
      '--scale': '1.08',
    },
  },
  {
    id: 7,
    style: {
      '--size': '18rem',
      '--duration': '38s',
      '--delay': '-16s',
      '--start-left': '8%',
      '--start-top': '-22rem',
      '--move-x': '10vw',
      '--move-y': '120vh',
      '--opacity': '0.48',
    },
  },
  {
    id: 8,
    style: {
      '--size': '13rem',
      '--duration': '29s',
      '--delay': '-8s',
      '--start-left': '26%',
      '--start-top': '-18rem',
      '--move-x': '-7vw',
      '--move-y': '114vh',
      '--opacity': '0.6',
      '--scale': '0.9',
    },
  },
  {
    id: 9,
    style: {
      '--size': '20rem',
      '--duration': '40s',
      '--delay': '-20s',
      '--start-left': '44%',
      '--start-top': '-24rem',
      '--move-x': '12vw',
      '--move-y': '122vh',
      '--opacity': '0.46',
    },
  },
  {
    id: 10,
    style: {
      '--size': '15rem',
      '--duration': '33s',
      '--delay': '-12s',
      '--start-left': '63%',
      '--start-top': '-16rem',
      '--move-x': '-9vw',
      '--move-y': '118vh',
      '--opacity': '0.58',
    },
  },
  {
    id: 11,
    style: {
      '--size': '19rem',
      '--duration': '36s',
      '--delay': '-26s',
      '--start-left': '82%',
      '--start-top': '-20rem',
      '--move-x': '8vw',
      '--move-y': '116vh',
      '--opacity': '0.5',
      '--blur': '0.9px',
    },
  },
  {
    id: 12,
    style: {
      '--size': '21rem',
      '--duration': '45s',
      '--delay': '-18s',
      '--start-left': '-24rem',
      '--start-top': '12%',
      '--move-x': '125vw',
      '--move-y': '-20vh',
      '--opacity': '0.44',
      '--scale': '1.05',
    },
  },
  {
    id: 13,
    style: {
      '--size': '14rem',
      '--duration': '31s',
      '--delay': '-9s',
      '--start-left': '-18rem',
      '--start-top': '34%',
      '--move-x': '118vw',
      '--move-y': '22vh',
      '--opacity': '0.6',
    },
  },
  {
    id: 14,
    style: {
      '--size': '17rem',
      '--duration': '37s',
      '--delay': '-3s',
      '--start-left': '-22rem',
      '--start-top': '58%',
      '--move-x': '130vw',
      '--move-y': '-32vh',
      '--opacity': '0.52',
      '--blur': '1px',
    },
  },
  {
    id: 15,
    style: {
      '--size': '12rem',
      '--duration': '27s',
      '--delay': '-21s',
      '--start-left': '-16rem',
      '--start-top': '78%',
      '--move-x': '116vw',
      '--move-y': '28vh',
      '--opacity': '0.62',
    },
  },
  {
    id: 16,
    style: {
      '--size': '23rem',
      '--duration': '47s',
      '--delay': '-7s',
      '--start-right': '-26rem',
      '--start-top': '18%',
      '--move-x': '-130vw',
      '--move-y': '24vh',
      '--opacity': '0.42',
      '--scale': '1.1',
    },
  },
  {
    id: 17,
    style: {
      '--size': '15rem',
      '--duration': '32s',
      '--delay': '-15s',
      '--start-right': '-18rem',
      '--start-top': '42%',
      '--move-x': '-118vw',
      '--move-y': '-18vh',
      '--opacity': '0.6',
    },
  },
  {
    id: 18,
    style: {
      '--size': '19rem',
      '--duration': '39s',
      '--delay': '-24s',
      '--start-right': '-22rem',
      '--start-top': '66%',
      '--move-x': '-124vw',
      '--move-y': '32vh',
      '--opacity': '0.48',
      '--blur': '1.1px',
    },
  },
]
</script>

<template>
  <div class="animated-bubble-background">
    <span
      v-for="bubble in bubbleConfigs"
      :key="bubble.id"
      class="bubble"
      :style="bubble.style"
    />
  </div>
</template>

<style scoped>
.animated-bubble-background {
  position: absolute;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  z-index: 0;
}

.animated-bubble-background::before {
  content: '';
  position: absolute;
  inset: -45%;
  background:
    radial-gradient(circle at 25% 20%, rgba(22, 163, 74, 0.58), transparent 62%),
    radial-gradient(circle at 72% 78%, rgba(2, 132, 199, 0.55), transparent 60%),
    radial-gradient(circle at 60% 10%, rgba(56, 189, 248, 0.48), transparent 65%),
    linear-gradient(120deg, rgba(5, 150, 105, 0.24), rgba(37, 99, 235, 0.2));
  filter: blur(50px) saturate(120%);
  opacity: 0.95;
  animation: glowPulse 22s ease-in-out infinite alternate;
}

.bubble {
  position: absolute;
  top: var(--start-top, auto);
  bottom: var(--start-bottom, auto);
  left: var(--start-left, auto);
  right: var(--start-right, auto);
  width: var(--size, 14rem);
  height: var(--size, 14rem);
  border-radius: 9999px;
  background:
    radial-gradient(
      circle at 38% 35%,
      rgba(16, 185, 129, 0.92),
      rgba(5, 150, 105, 0.65) 45%,
      rgba(2, 132, 199, 0.32) 75%
    );
  box-shadow:
    0 0 65px rgba(16, 185, 129, 0.32),
    0 0 120px rgba(2, 132, 199, 0.22);
  opacity: var(--opacity, 0.55);
  mix-blend-mode: screen;
  filter: blur(var(--blur, 0px));
  animation: bubbleFloat var(--duration, 38s) linear infinite;
  animation-delay: var(--delay, 0s);
  transform: translate3d(0, 0, 0) scale(var(--scale, 1));
}

@keyframes bubbleFloat {
  0% {
    transform: translate3d(0, 0, 0) scale(var(--scale, 1));
    opacity: 0;
  }
  10% {
    opacity: var(--opacity, 0.55);
  }
  55% {
    transform:
      translate3d(
        calc(var(--move-x, 0px) * 0.45),
        calc(var(--move-y, -110vh) * 0.45),
        0
      )
      scale(calc(var(--scale, 1) * 1.05));
    opacity: var(--opacity, 0.55);
  }
  100% {
    transform:
      translate3d(
        var(--move-x, 0px),
        var(--move-y, -110vh),
        0
      )
      scale(calc(var(--scale, 1) * 0.95));
    opacity: 0;
  }
}

@keyframes glowPulse {
  0% {
    transform: scale(1) rotate(0deg);
    opacity: 0.6;
  }
  100% {
    transform: scale(1.1) rotate(4deg);
    opacity: 0.9;
  }
}
</style>
